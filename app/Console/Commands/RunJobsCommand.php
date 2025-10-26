<?php

namespace App\Console\Commands;

use App\Jobs\DownloadRecordingJob;
use App\Jobs\TranscribeRecordingJob;
use App\Models\Job as DomainJob;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunJobsCommand extends Command
{
    protected $signature = 'jobs:run {--once : Process only a single polling cycle} {--sleep=5 : Seconds to sleep when no jobs are available} {--max=0 : Maximum jobs to process in this invocation (0 = unlimited)}';

    protected $description = 'Process domain jobs such as recording downloads without relying on a long-running worker.';

    public function __construct(private readonly SettingsService $settings)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $once = (bool) $this->option('once');
        $sleep = max(1, (int) $this->option('sleep'));
        $max = max(0, (int) $this->option('max'));
        $processed = 0;
        $now = CarbonImmutable::now();

        $this->settings->set('system.jobs.last_ran_at', $now->toIso8601String());

        while (true) {
            $job = $this->reserveJob();

            if ($job === null) {
                if ($once || ($max > 0 && $processed >= $max)) {
                    break;
                }

                sleep($sleep);

                continue;
            }

            $payload = $job->payload ?? [];

            try {
                if ($this->executeJob($job, $payload)) {
                    ++$processed;
                }
            } catch (Throwable $throwable) {
                $job->refresh();

                if ($job->status === 'running') {
                    $job->status = 'failed';
                    $job->last_error = $throwable->getMessage();
                    $job->run_at = CarbonImmutable::now();
                    $job->save();
                }

                Log::error('Domain job execution failed.', [
                    'job_id' => $job->id,
                    'type' => $job->type,
                    'message' => $throwable->getMessage(),
                ]);
            }

            if ($once) {
                break;
            }

            if ($max > 0 && $processed >= $max) {
                break;
            }
        }

        return self::SUCCESS;
    }

    private function reserveJob(): ?DomainJob
    {
        return DB::transaction(function () {
            $job = DomainJob::query()
                ->whereIn('type', ['download', 'transcribe'])
                ->where('status', 'queued')
                ->where(function ($query) {
                    $query->whereNull('run_at')->orWhere('run_at', '<=', CarbonImmutable::now());
                })
                ->orderByRaw("FIELD(type, 'download', 'transcribe')")
                ->orderBy('run_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($job !== null) {
                $job->status = 'running';
                $job->run_at = CarbonImmutable::now();
                $job->save();
            }

            return $job;
        }, 3);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function executeJob(DomainJob $job, array $payload): bool
    {
        $recordingId = (int) ($payload['recording_id'] ?? 0);

        if ($recordingId <= 0) {
            $job->status = 'failed';
            $job->last_error = 'Invalid recording identifier in payload.';
            $job->run_at = CarbonImmutable::now();
            $job->save();
            $this->warn(sprintf('Job %d missing recording_id, marking as failed.', $job->id));

            return false;
        }

        if ($job->type === 'download') {
            DownloadRecordingJob::dispatchSync($recordingId, $job->id);

            return true;
        }

        if ($job->type === 'transcribe') {
            $engine = (string) ($payload['engine'] ?? '');
            $language = $payload['language'] ?? null;

            if ($engine === '') {
                $job->status = 'failed';
                $job->last_error = 'Transcription job missing engine setting.';
                $job->run_at = CarbonImmutable::now();
                $job->save();
                $this->warn(sprintf('Transcription job %d missing engine, marking as failed.', $job->id));

                return false;
            }

            TranscribeRecordingJob::dispatchSync($recordingId, $engine, $job->id, is_string($language) ? $language : null);

            return true;
        }

        $job->status = 'failed';
        $job->last_error = sprintf('Unsupported job type [%s].', $job->type);
        $job->run_at = CarbonImmutable::now();
        $job->save();

        return false;
    }
}
