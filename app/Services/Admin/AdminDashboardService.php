<?php

namespace App\Services\Admin;

use App\Models\Call;
use App\Models\Job;
use App\Models\Recording;
use App\Models\Transcript;
use App\Services\Admin\Data\DiskUsageSnapshot;
use App\Services\Admin\Data\StorageBackendBreakdown;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminDashboardService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function metrics(): AdminDashboardMetrics
    {
        $now = CarbonImmutable::now();
        $since = $now->subDay();

        $lastPollAt = $this->lastPollAt();
        $callsIngested = $this->countCallsSince($since);
        $recordingsReady = $this->countRecordingsReadySince($since);
        $transcriptsReady = $this->countTranscriptsReadySince($since);
        $domainFailures = $this->countDomainFailuresSince($since);
        $queueFailures = $this->countQueueFailures();
        $queueDepth = $this->countQueuedDomainJobs();
        $queueBacklog = $this->countQueueBacklog();
        $diskUsage = $this->determineDiskUsage();
        $storageBreakdown = $this->storageBreakdown();

        $alerts = $this->buildAlerts($diskUsage, $domainFailures + $queueFailures);

        return new AdminDashboardMetrics(
            generatedAt: $now,
            lastPollAt: $lastPollAt,
            callsIngested24h: $callsIngested,
            recordingsReady24h: $recordingsReady,
            transcriptsReady24h: $transcriptsReady,
            domainFailedJobs24h: $domainFailures,
            queueFailedJobs: $queueFailures,
            queueDepth: $queueDepth,
            queueBacklog: $queueBacklog,
            diskUsage: $diskUsage,
            storageBreakdown: $storageBreakdown,
            alerts: $alerts,
        );
    }

    private function lastPollAt(): ?CarbonImmutable
    {
        $value = $this->settings->get('telephony.provider.last_polled_at');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function countCallsSince(CarbonImmutable $since): int
    {
        return Call::query()->where('created_at', '>=', $since)->count();
    }

    private function countRecordingsReadySince(CarbonImmutable $since): int
    {
        return Recording::query()
            ->where('status', 'ready')
            ->where('updated_at', '>=', $since)
            ->count();
    }

    private function countTranscriptsReadySince(CarbonImmutable $since): int
    {
        return Transcript::query()
            ->where('status', 'ready')
            ->where('updated_at', '>=', $since)
            ->count();
    }

    private function countDomainFailuresSince(CarbonImmutable $since): int
    {
        return Job::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', $since)
            ->count();
    }

    private function countQueueFailures(): int
    {
        return (int) DB::table('failed_jobs')->count();
    }

    private function countQueuedDomainJobs(): int
    {
        return Job::query()->where('status', 'queued')->count();
    }

    private function countQueueBacklog(): int
    {
        return (int) DB::table('queue_jobs')->count();
    }

    /**
     * @return list<StorageBackendBreakdown>
     */
    private function storageBreakdown(): array
    {
        $raw = Recording::query()
            ->select('storage_backend')
            ->selectRaw('COUNT(*) as total_recordings')
            ->selectRaw('COALESCE(SUM(bytes), 0) as total_bytes')
            ->groupBy('storage_backend')
            ->get();

        $indexed = $raw->mapWithKeys(function ($row): array {
            $backend = (string) $row->storage_backend;

            return [
                $backend => new StorageBackendBreakdown(
                    backend: $backend,
                    recordings: (int) $row->total_recordings,
                    bytes: (int) $row->total_bytes,
                ),
            ];
        });

        return $this->ensureBackends($indexed)->values()->all();
    }

    private function ensureBackends(Collection $indexed): Collection
    {
        foreach (['local', 's3'] as $backend) {
            if ($indexed->has($backend)) {
                continue;
            }

            $indexed->put($backend, new StorageBackendBreakdown($backend, 0, 0));
        }

        return $indexed->sortBy(fn (StorageBackendBreakdown $breakdown): string => $breakdown->backend);
    }

    private function determineDiskUsage(): DiskUsageSnapshot
    {
        $storagePath = storage_path('app');
        $total = @disk_total_space($storagePath);
        $free = @disk_free_space($storagePath);

        if ($total === false || $free === false || $total <= 0) {
            return new DiskUsageSnapshot(null, null, null, null);
        }

        $used = $total - $free;
        $percent = $total > 0 ? ($used / $total) * 100 : null;

        return new DiskUsageSnapshot(
            totalBytes: (int) $total,
            usedBytes: (int) $used,
            freeBytes: (int) $free,
            usedPercent: $percent !== null ? (float) $percent : null,
        );
    }

    /**
     * @return list<string>
     */
    private function buildAlerts(DiskUsageSnapshot $diskUsage, int $totalFailures): array
    {
        $alerts = [];

        if ($diskUsage->usedPercent !== null && $diskUsage->usedPercent >= 80.0) {
            $alerts[] = sprintf(
                'Local storage usage is at %.1f%%. Consider rotating or migrating recordings.',
                $diskUsage->usedPercent
            );
        }

        if ($totalFailures > 0) {
            $alerts[] = sprintf(
                '%d %s detected in the last 24 hours.',
                $totalFailures,
                Str::plural('job failure', $totalFailures)
            );
        }

        return $alerts;
    }
}
