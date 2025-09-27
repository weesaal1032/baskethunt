<?php

namespace App\Services\Admin;

use App\Services\Admin\Data\DiskUsageSnapshot;
use App\Services\Admin\Data\StorageBackendBreakdown;
use Carbon\CarbonImmutable;

final class AdminDashboardMetrics
{
    /**
     * @param list<string> $alerts
     * @param list<StorageBackendBreakdown> $storageBreakdown
     */
    public function __construct(
        public readonly CarbonImmutable $generatedAt,
        public readonly ?CarbonImmutable $lastPollAt,
        public readonly int $callsIngested24h,
        public readonly int $recordingsReady24h,
        public readonly int $transcriptsReady24h,
        public readonly int $domainFailedJobs24h,
        public readonly int $queueFailedJobs,
        public readonly int $queueDepth,
        public readonly int $queueBacklog,
        public readonly DiskUsageSnapshot $diskUsage,
        public readonly array $storageBreakdown,
        public readonly array $alerts,
    ) {
    }
}
