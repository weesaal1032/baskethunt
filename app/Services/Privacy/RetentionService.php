<?php

namespace App\Services\Privacy;

use App\Models\Call;
use App\Models\Recording;
use App\Models\Transcript;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\Data\CallDeletionOutcome;
use App\Services\Privacy\Data\RetentionOutcome;
use App\Services\Settings\SettingsService;
use App\Services\Storage\StorageService;
use App\Repositories\Contracts\RecordingsRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RetentionService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RecordingsRepositoryInterface $recordings,
        private readonly StorageService $storage,
        private readonly AuditLogger $audit,
    ) {
    }

    public function enforce(bool $dryRun = false): RetentionOutcome
    {
        $retentionMonths = max((int) $this->settings->get('privacy.retention_months', 12), 1);
        $graceDays = max((int) $this->settings->get('privacy.deletion_grace_days', 14), 1);

        $now = CarbonImmutable::now();
        $softDeleteBefore = $now->subMonthsNoOverflow($retentionMonths);
        $purgeBefore = $now->subDays($graceDays);

        $softDeletedRecordings = 0;
        $softDeletedTranscripts = 0;
        $purgedRecordings = 0;
        $purgedTranscripts = 0;

        $this->recordings->retentionCandidates($softDeleteBefore)->each(function (Recording $recording) use (&$softDeletedRecordings, &$softDeletedTranscripts, $dryRun): void {
            $call = $recording->call;
            $transcript = $recording->transcript;

            $context = [
                'action' => 'soft_delete',
                'dry_run' => $dryRun,
                'recording_id' => $recording->id,
                'call_id' => $recording->call_id,
                'provider_call_id' => $call?->provider_call_id,
                'started_at' => optional($call?->started_at)?->toIso8601String(),
            ];

            if ($dryRun) {
                Log::info('Retention dry-run: would soft delete recording.', $context);
                if ($transcript !== null && ! $transcript->trashed()) {
                    Log::info('Retention dry-run: would soft delete transcript.', $context + ['transcript_id' => $transcript->id]);
                }

                return;
            }

            if ($transcript !== null && ! $transcript->trashed()) {
                $transcript->delete();
                $softDeletedTranscripts++;
                $this->audit->logSystem('transcript.retention.soft_delete', $transcript, $context + ['transcript_id' => $transcript->id]);
            }

            if (! $recording->trashed()) {
                $recording->delete();
                $softDeletedRecordings++;
                $this->audit->logSystem('recording.retention.soft_delete', $recording, $context);
                Log::info('Retention: soft deleted recording.', $context);
            }
        });

        $this->recordings->purgeCandidates($purgeBefore)->each(function (Recording $recording) use (&$purgedRecordings, &$purgedTranscripts, $dryRun): void {
            $call = $recording->call;
            $transcript = $recording->transcript;

            $context = [
                'action' => 'purge',
                'dry_run' => $dryRun,
                'recording_id' => $recording->id,
                'call_id' => $recording->call_id,
                'provider_call_id' => $call?->provider_call_id,
                'deleted_at' => optional($recording->deleted_at)?->toIso8601String(),
            ];

            if ($dryRun) {
                Log::info('Retention dry-run: would purge recording.', $context);
                if ($transcript !== null) {
                    Log::info('Retention dry-run: would purge transcript.', $context + ['transcript_id' => $transcript->id]);
                }

                return;
            }

            if ($transcript instanceof Transcript) {
                $this->audit->logSystem('transcript.retention.purge', $transcript, $context + ['transcript_id' => $transcript->id]);
                $transcript->forceDelete();
                $purgedTranscripts++;
            }

            $path = $recording->local_path;

            if (is_string($path) && $path !== '') {
                try {
                    $this->storage->deleteRecording($path, $recording->storage_backend);
                    Log::info('Retention: deleted stored recording object.', $context + ['storage_path' => $path, 'storage_backend' => $recording->storage_backend]);
                } catch (Throwable $exception) {
                    Log::warning('Retention: failed to delete stored recording object.', $context + ['storage_path' => $path, 'error' => $exception->getMessage()]);
                }
            }

            $this->audit->logSystem('recording.retention.purge', $recording, $context);
            $recording->forceDelete();
            $purgedRecordings++;
            Log::info('Retention: purged recording.', $context);
        });

        return new RetentionOutcome(
            $dryRun,
            $softDeleteBefore,
            $purgeBefore,
            $softDeletedRecordings,
            $softDeletedTranscripts,
            $purgedRecordings,
            $purgedTranscripts,
        );
    }

    public function deleteCall(int $callId, bool $dryRun = false): ?CallDeletionOutcome
    {
        $call = Call::query()
            ->with([
                'recordings' => function ($query): void {
                    $query->withTrashed()->with(['transcript' => fn ($transcriptQuery) => $transcriptQuery->withTrashed()]);
                },
                'qaScores',
            ])
            ->find($callId);

        if ($call === null) {
            return null;
        }

        $recordingsDeleted = 0;
        $transcriptsDeleted = 0;
        $storageObjectsDeleted = 0;

        foreach ($call->recordings as $recording) {
            $transcript = $recording->transcript;
            $context = [
                'dry_run' => $dryRun,
                'call_id' => $call->id,
                'provider_call_id' => $call->provider_call_id,
                'recording_id' => $recording->id,
                'storage_backend' => $recording->storage_backend,
            ];

            if ($dryRun) {
                Log::info('Delete-call dry-run: would remove recording.', $context);
                if ($transcript !== null) {
                    Log::info('Delete-call dry-run: would remove transcript.', $context + ['transcript_id' => $transcript->id]);
                }

                continue;
            }

            if ($transcript instanceof Transcript) {
                $this->audit->logSystem('transcript.delete_request', $transcript, $context + ['transcript_id' => $transcript->id]);
                $transcript->forceDelete();
                $transcriptsDeleted++;
            }

            $path = $recording->local_path;
            if (is_string($path) && $path !== '') {
                try {
                    $this->storage->deleteRecording($path, $recording->storage_backend);
                    $storageObjectsDeleted++;
                } catch (Throwable $exception) {
                    Log::warning('Delete-call request failed to delete stored recording object.', $context + ['storage_path' => $path, 'error' => $exception->getMessage()]);
                }
            }

            $this->audit->logSystem('recording.delete_request', $recording, $context);
            $recordingsDeleted++;
            $recording->forceDelete();
        }

        if (! $dryRun) {
            $this->audit->logSystem('call.delete_request', $call, [
                'call_id' => $call->id,
                'provider_call_id' => $call->provider_call_id,
                'recordings_deleted' => $recordingsDeleted,
                'transcripts_deleted' => $transcriptsDeleted,
            ]);

            $call->delete();
            Log::info('Delete-call request completed.', [
                'call_id' => $call->id,
                'provider_call_id' => $call->provider_call_id,
                'recordings_deleted' => $recordingsDeleted,
                'transcripts_deleted' => $transcriptsDeleted,
                'storage_objects_deleted' => $storageObjectsDeleted,
            ]);
        }

        return new CallDeletionOutcome(
            $call->id,
            $call->provider_call_id,
            $dryRun,
            $recordingsDeleted,
            $transcriptsDeleted,
            $storageObjectsDeleted,
        );
    }
}
