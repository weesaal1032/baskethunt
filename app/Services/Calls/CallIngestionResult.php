<?php

namespace App\Services\Calls;

class CallIngestionResult
{
    public function __construct(
        public readonly int $processed,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $downloadsQueued,
        public readonly ?string $nextCursor,
    ) {
    }
}
