<?php

namespace App\Services\Health;

use App\Jobs\TranscribeRecordingJob;
use App\Models\Call;
use App\Models\Job as DomainJob;
use App\Models\Provider;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Transcript;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use App\Services\Calls\CallReportingService;
use App\Services\Providers\TelephonyClientInterface;
use App\Services\Qa\QaScoringService;
use App\Services\Settings\SettingsService;
use App\Services\Storage\StorageService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class E2eHealthCheckService
{
    private const SAMPLE_REMOTE_URL = 'https://example.com/callhub-e2e.mp3';

    public function __construct(
        private readonly TelephonyClientInterface $telephonyClient,
        private readonly SettingsService $settings,
        private readonly StorageService $storage,
        private readonly QaScoringService $qaScoring,
        private readonly CallReportingService $callReporting,
        private readonly AdminDashboardService $dashboard,
        private readonly Gate $gate
    ) {
    }

    public function run(User $admin): E2eHealthCheckReport
    {
        $steps = [];
        $call = null;
        $recording = null;
        $transcript = null;
        $qaScore = null;
        $metrics = [];

        $steps[] = $this->execute('telephony', 'Telephony connectivity', function () use (&$metrics): array {
            $result = $this->telephonyClient->testConnectivity();
            $status = (string) ($result['status'] ?? 'unknown');

            return [
                'message' => $status === 'skipped'
                    ? 'Connectivity check skipped until a provider base URL is configured.'
                    : 'Telephony provider responded successfully.',
                'details' => $result,
            ];
        });

        $steps[] = $this->execute('ingestion', 'Create diagnostic call record', function () use (&$call, &$recording, $admin): array {
            [$call, $recording] = $this->provisionCall($admin);

            return [
                'message' => sprintf('Created call %s with pending recording.', $call->provider_call_id),
                'details' => ['call_id' => $call->id, 'recording_id' => $recording->id],
            ];
        });

        $steps[] = $this->execute('download', 'Simulate recording download', function () use (&$recording, $call): array {
            if (! $call instanceof Call || ! $recording instanceof Recording) {
                throw new RuntimeException('Diagnostic call was not provisioned.');
            }

            $bytes = $this->sampleAudio();
            $tempPath = $this->writeTempFile($bytes);
            $handle = fopen($tempPath, 'rb');

            if ($handle === false) {
                throw new RuntimeException('Unable to open simulated audio payload.');
            }

            try {
                $stored = $this->storage->storeRecording($call->provider_call_id, $handle, 'mp3', $recording->storage_backend);
            } finally {
                fclose($handle);
                @unlink($tempPath);
            }

            $recording->forceFill([
                'local_path' => $stored->path,
                'format' => 'mp3',
                'bytes' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'waveform_json' => json_encode($this->mockWaveform(), JSON_THROW_ON_ERROR),
                'status' => 'ready',
            ])->save();

            return [
                'message' => 'Stored simulated recording payload and marked ready.',
                'details' => ['path' => $recording->local_path, 'bytes' => $recording->bytes],
            ];
        });

        $steps[] = $this->execute('transcription', 'Generate mock transcript', function () use (&$recording, &$transcript): array {
            if (! $recording instanceof Recording) {
                throw new RuntimeException('Recording not available for transcription.');
            }

            $job = new DomainJob();
            $job->type = 'transcribe';
            $job->payload = ['recording_id' => $recording->id];
            $job->status = 'queued';
            $job->attempts = 0;
            $job->run_at = CarbonImmutable::now();
            $job->save();

            TranscribeRecordingJob::dispatchSync($recording->id, 'mock', $job->id);

            $recording->refresh();
            $transcript = $recording->transcript;

            if (! $transcript instanceof Transcript || $transcript->status !== 'ready') {
                throw new RuntimeException('Mock transcription did not complete.');
            }

            return [
                'message' => 'Mock transcription generated successfully.',
                'details' => ['transcript_id' => $transcript->id],
            ];
        });

        $steps[] = $this->execute('qa', 'Submit QA score', function () use (&$qaScore, $call, $transcript): array {
            if (! $call instanceof Call || ! $transcript instanceof Transcript) {
                throw new RuntimeException('Call or transcript missing for QA scoring.');
            }

            $qaUser = $this->ensureQaUser();
            $rubric = $this->ensureRubric();
            $responses = $this->synthesiseResponses($rubric);

            $qaScore = $this->qaScoring->submit($call, $qaUser, [
                'version' => 1,
                'rubric_version' => $this->qaScoring->rubricVersion(),
                'responses' => $responses,
                'passed' => true,
                'comment' => 'Automated diagnostics QA score.',
                'tags' => ['e2e'],
            ]);

            return [
                'message' => 'QA score recorded and marked as submitted.',
                'details' => ['qa_score_id' => $qaScore->id],
            ];
        });

        $steps[] = $this->execute('rbac', 'Verify RBAC for recordings', function () use ($recording): array {
            if (! $recording instanceof Recording) {
                throw new RuntimeException('Recording missing for RBAC verification.');
            }

            $qaUser = $this->ensureQaUser();
            $readonly = $this->ensureReadonlyUser();

            $qaCanView = $this->gate->forUser($qaUser)->allows('view', $recording);
            $readonlyCanView = $this->gate->forUser($readonly)->allows('view', $recording);

            if (! $qaCanView || $readonlyCanView) {
                throw new RuntimeException('RBAC enforcement failed for recording access.');
            }

            return [
                'message' => 'QA role authorised while readonly access was denied.',
            ];
        });

        $steps[] = $this->execute('export', 'Generate CSV export snapshot', function () use ($call): array {
            if (! $call instanceof Call) {
                throw new RuntimeException('Call missing for export validation.');
            }

            $handle = fopen('php://temp', 'w+b');

            if ($handle === false) {
                throw new RuntimeException('Unable to allocate export buffer.');
            }

            $found = false;

            try {
                $this->callReporting->stream([])->each(function (Call $reportCall) use ($handle, $call, &$found): void {
                    $row = $this->callReporting->transform($reportCall, true);

                    if ($reportCall->id === $call->id) {
                        $found = true;
                    }

                    fputcsv($handle, array_map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value), $row));
                });

                rewind($handle);
                $size = strlen(stream_get_contents($handle) ?: '');
            } finally {
                fclose($handle);
            }

            if (! $found) {
                throw new RuntimeException('Diagnostic call missing from export output.');
            }

            return [
                'message' => 'CSV export generated for diagnostic data.',
                'details' => ['bytes' => $size],
            ];
        });

        $steps[] = $this->execute('dashboard', 'Refresh dashboard metrics', function () use (&$metrics): array {
            $dashboard = $this->dashboard->metrics();

            $metrics = [
                'calls_24h' => $dashboard->callsIngested24h,
                'recordings_24h' => $dashboard->recordingsReady24h,
                'transcripts_24h' => $dashboard->transcriptsReady24h,
                'queue_depth' => $dashboard->queueDepth,
                'alerts' => $dashboard->alerts,
            ];

            return [
                'message' => 'Dashboard metrics generated.',
                'details' => $metrics,
            ];
        });

        $successful = collect($steps)->every(static fn (E2eHealthCheckStep $step): bool => $step->passed);

        return new E2eHealthCheckReport($successful, $steps, $call, $recording, $transcript, $qaScore, $metrics);
    }

    /**
     * @param callable(): array{message: string, details?: array<string, mixed>} $operation
     */
    private function execute(string $key, string $label, callable $operation): E2eHealthCheckStep
    {
        try {
            $result = $operation();

            return new E2eHealthCheckStep(
                key: $key,
                label: $label,
                passed: true,
                message: $result['message'] ?? 'Completed successfully.',
                details: $result['details'] ?? []
            );
        } catch (\Throwable $throwable) {
            report($throwable);

            return new E2eHealthCheckStep(
                key: $key,
                label: $label,
                passed: false,
                message: $throwable->getMessage(),
            );
        }
    }

    /**
     * @return array{0: Call, 1: Recording}
     */
    private function provisionCall(User $admin): array
    {
        $provider = Provider::query()->first();

        if (! $provider instanceof Provider) {
            $provider = Provider::create([
                'name' => 'Diagnostics Provider',
                'base_url' => 'https://example.com/api',
                'auth_type' => 'header',
                'api_key' => null,
            ]);
        }

        $agent = $this->ensureQaUser();

        $providerCallId = 'E2E-'.Str::uuid()->toString();
        $now = CarbonImmutable::now();

        $call = Call::updateOrCreate(
            ['provider_call_id' => $providerCallId],
            [
                'provider_id' => $provider->id,
                'from_number' => '+15551234567',
                'to_number' => '+15557654321',
                'direction' => 'outbound',
                'agent_id' => $agent->id,
                'started_at' => $now->subMinutes(5),
                'ended_at' => $now->subMinutes(4),
                'duration_sec' => 60,
                'disposition' => 'completed',
                'queue' => 'Diagnostics',
                'metadata' => [
                    'source' => 'e2e-health-check',
                    'initiated_by' => $admin->email,
                ],
            ]
        );

        $recording = Recording::updateOrCreate(
            ['call_id' => $call->id],
            [
                'remote_url' => self::SAMPLE_REMOTE_URL,
                'storage_backend' => (string) ($this->settings->get('storage.default') ?? 'local'),
                'status' => 'pending',
            ]
        );

        $this->settings->set('telephony.provider.last_polled_at', $now->toIso8601String());

        return [$call, $recording];
    }

    private function ensureQaUser(): User
    {
        $email = 'qa-diagnostics@example.com';

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'QA Diagnostics',
                'role' => 'qa',
                'password' => Hash::make(Str::random(40)),
                'status' => 'active',
            ]
        );
    }

    private function ensureReadonlyUser(): User
    {
        $email = 'readonly-diagnostics@example.com';

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Readonly Diagnostics',
                'role' => 'readonly',
                'password' => Hash::make(Str::random(40)),
                'status' => 'active',
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ensureRubric(): array
    {
        $rubric = $this->qaScoring->rubric();

        if ($rubric !== []) {
            return $rubric;
        }

        $rubric = [
            [
                'id' => 'greeting',
                'name' => 'Greeting',
                'weight' => 50,
                'questions' => [
                    [
                        'id' => 'greet_customer',
                        'prompt' => 'Agent greeted the customer.',
                        'type' => 'yes_no',
                        'weight' => 50,
                    ],
                ],
            ],
            [
                'id' => 'compliance',
                'name' => 'Compliance',
                'weight' => 50,
                'questions' => [
                    [
                        'id' => 'disclosure',
                        'prompt' => 'Mandatory disclosures completed.',
                        'type' => 'scale',
                        'weight' => 50,
                        'scale_min' => 0,
                        'scale_max' => 5,
                    ],
                ],
            ],
        ];

        $this->settings->setMany([
            'qa.rubric' => $rubric,
            'qa.rubric_version' => 1,
        ]);

        return $rubric;
    }

    /**
     * @param array<int, array<string, mixed>> $rubric
     * @return array<string, mixed>
     */
    private function synthesiseResponses(array $rubric): array
    {
        $responses = [];

        foreach ($rubric as $category) {
            foreach (Arr::get($category, 'questions', []) as $question) {
                $id = (string) Arr::get($question, 'id');
                $type = (string) Arr::get($question, 'type', 'yes_no');

                if ($id === '') {
                    continue;
                }

                if ($type === 'scale') {
                    $responses[$id] = (int) Arr::get($question, 'scale_max', 5);
                } else {
                    $responses[$id] = true;
                }
            }
        }

        return $responses;
    }

    private function sampleAudio(): string
    {
        return 'CallHub diagnostics audio sample '.CarbonImmutable::now()->format('U');
    }

    private function writeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'callhub-e2e-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate temporary path for simulated download.');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Failed to write simulated audio payload.');
        }

        return $path;
    }

    /**
     * @return array<int, float>
     */
    private function mockWaveform(): array
    {
        return [0.0, 0.25, 0.5, 0.35, 0.1];
    }
}
