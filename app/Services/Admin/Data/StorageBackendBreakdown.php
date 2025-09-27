<?php

namespace App\Services\Admin\Data;

final class StorageBackendBreakdown
{
    public function __construct(
        public readonly string $backend,
        public readonly int $recordings,
        public readonly int $bytes,
    ) {
    }
}
