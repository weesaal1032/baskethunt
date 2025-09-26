<?php

namespace App\Services\Admin\Data;

final class DiskUsageSnapshot
{
    public function __construct(
        public readonly ?int $totalBytes,
        public readonly ?int $usedBytes,
        public readonly ?int $freeBytes,
        public readonly ?float $usedPercent,
    ) {
    }
}
