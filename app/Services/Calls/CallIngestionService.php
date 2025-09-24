<?php

namespace App\Services\Calls;

use App\Jobs\DownloadRecordingJob;
use App\Models\Call;
use App\Models\Job as DomainJob;
use App\Models\Provider;
use App\Models\Recording;
use App\Services\Providers\TelephonyClientInterface;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class CallIngestionService
{
    public function __construct(
        private readonly TelephonyClientInterface $telephonyClient,
        private readonly SettingsService $settings,
    ) {
    }

    public function poll(): CallIngestionResult
    {
        $pollWindowDays = max(1, (int) $this->settings->get('telephony.provider.poll_window_days', 7));
        $now = CarbonImmutable::now();
        $from = $now->subDays($pollWindowDays);
        $cursor = $this->settings->get('telephony.provider.cursor');
        $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;
        $providerId = $this->resolveProviderId();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $downloadsQueued = 0;
        $seenCursors = [];
        $currentCursor = $cursor;
        $lastCursor = $cursor;

        do {
            if ($currentCursor !== null) {
                if (isset($seenCursors[$currentCursor])) {
                    Log::warning('Telephony polling cursor loop detected; aborting to prevent infinite processing.', [
                        'cursor' => $currentCursor,
                    ]);
                    break;
                }

                $seenCursors[$currentCursor] = true;
            }

            $page = $this->telephonyClient->listCalls($from, $now, $currentCursor);
            $calls = $page->calls();
            $processed += $calls->count();

            foreach ($calls as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                [$callCreated, $callUpdated, $downloadQueued] = $this->ingestCall($providerId, $payload);

                if ($callCreated) {
                    ++$created;
                }

                if ($callUpdated) {
                    ++$updated;
                }

                if ($downloadQueued) {
                    ++$downloadsQueued;
                }
            }

            $nextCursor = $page->nextCursor();

            if ($nextCursor !== null) {
                $lastCursor = $nextCursor;
            }

            $currentCursor = $nextCursor;
        } while ($currentCursor !== null);

        $this->settings->set('telephony.provider.cursor', $lastCursor);
        $this->settings->set('telephony.provider.last_polled_at', $now->toIso8601String());

        return new CallIngestionResult($processed, $created, $updated, $downloadsQueued, $lastCursor);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: bool, 1: bool, 2: bool}
     */
    private function ingestCall(int $providerId, array $payload): array
    {
        $mapping = $this->mapping();
        $providerCallId = $this->stringValue(data_get($payload, $mapping['provider_call_id'] ?? 'id'));

        if ($providerCallId === null) {
            return [false, false, false];
        }

        return DB::transaction(function () use ($providerId, $payload, $mapping, $providerCallId): array {
            $call = Call::query()->where('provider_call_id', $providerCallId)->first();

            if ($call === null) {
                $call = new Call(['provider_call_id' => $providerCallId]);
            }

            $wasExisting = $call->exists;
            $call->provider_id = $providerId;
            $call->from_number = $this->stringValue(data_get($payload, $mapping['from_number'] ?? 'from')) ?? 'Unknown';
            $call->to_number = $this->stringValue(data_get($payload, $mapping['to_number'] ?? 'to')) ?? 'Unknown';
            $call->direction = $this->normaliseDirection(
                $this->stringValue(data_get($payload, $mapping['direction'] ?? 'direction'))
            );
            $call->started_at = $this->normaliseTimestamp(data_get($payload, $mapping['started_at'] ?? 'started_at'));
            $call->ended_at = $this->normaliseTimestamp(data_get($payload, $mapping['ended_at'] ?? 'ended_at'));
            $call->duration_sec = $this->integerValue(data_get($payload, $mapping['duration'] ?? 'duration'));
            $call->disposition = $this->stringValue(data_get($payload, $mapping['status'] ?? 'status'));
            $call->metadata = $payload;

            $callDirty = $call->isDirty();
            $call->save();

            $recordingQueued = false;
            $recordingUrl = $this->stringValue(data_get($payload, $mapping['recording_url'] ?? 'recording_url'));

            if ($recordingUrl !== null) {
                $recordingQueued = $this->ensureRecordingQueued($call, $recordingUrl);
            }

            return [
                $call->wasRecentlyCreated,
                $wasExisting && $callDirty,
                $recordingQueued,
            ];
        });
    }

    private function ensureRecordingQueued(Call $call, string $remoteUrl): bool
    {
        $recording = Recording::query()->firstOrNew(['call_id' => $call->id]);
        $remoteChanged = ! $recording->exists || $recording->remote_url !== $remoteUrl;

        if ($remoteChanged) {
            $recording->remote_url = $remoteUrl;
            $recording->local_path = null;
            $recording->status = 'pending';
            $recording->storage_backend = $this->defaultStorageBackend();
            $recording->save();
        }

        if (! $remoteChanged) {
            return false;
        }

        if ($this->hasPendingDownloadJob($call, $remoteUrl)) {
            return false;
        }

        $job = new DomainJob();
        $job->type = 'download';
        $job->payload = [
            'call_id' => $call->id,
            'recording_id' => $recording->id,
            'remote_url' => $remoteUrl,
        ];
        $job->status = 'queued';
        $job->attempts = 0;
        $job->run_at = CarbonImmutable::now();
        $job->save();

        DownloadRecordingJob::dispatch($recording->id)->onQueue('recordings');

        return true;
    }

    private function hasPendingDownloadJob(Call $call, string $remoteUrl): bool
    {
        return DomainJob::query()
            ->where('type', 'download')
            ->whereIn('status', ['queued', 'running'])
            ->whereJsonContains('payload->call_id', $call->id)
            ->whereJsonContains('payload->remote_url', $remoteUrl)
            ->exists();
    }

    private function mapping(): array
    {
        $mapping = $this->settings->get('telephony.provider.mapping', []);

        if (! is_array($mapping)) {
            $mapping = [];
        }

        $mapping = array_filter(
            $mapping,
            static fn ($value): bool => is_string($value) && $value !== ''
        );

        $defaults = [
            'provider_call_id' => 'id',
            'from_number' => 'from',
            'to_number' => 'to',
            'started_at' => 'started_at',
            'ended_at' => 'ended_at',
            'duration' => 'duration',
            'status' => 'status',
            'recording_url' => 'recording_url',
            'direction' => 'direction',
        ];

        return array_merge($defaults, $mapping);
    }

    private function resolveProviderId(): int
    {
        $providerId = $this->settings->get('telephony.provider.provider_id');

        if ($providerId !== null && Provider::query()->whereKey((int) $providerId)->exists()) {
            return (int) $providerId;
        }

        $provider = Provider::query()->orderBy('id')->first();

        if ($provider === null) {
            throw new RuntimeException('No telephony provider configured for polling.');
        }

        return $provider->id;
    }

    private function defaultStorageBackend(): string
    {
        $backend = $this->settings->get('storage.default', 'local');

        return in_array($backend, ['local', 's3'], true) ? $backend : 'local';
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $string = (string) $value;

            return $string === '' ? null : $string;
        }

        return null;
    }

    private function integerValue(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return 0;
    }

    private function normaliseTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Exception $exception) {
                Log::warning('Failed to parse telephony timestamp.', [
                    'value' => $value,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function normaliseDirection(?string $direction): string
    {
        return match (Str::of((string) $direction)->lower()->value()) {
            'outbound' => 'outbound',
            default => 'inbound',
        };
    }
}
