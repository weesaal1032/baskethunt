<?php

namespace App\Services\Privacy\Data;

use Carbon\CarbonImmutable;

final class RetentionOutcome
{
    public function __construct(
        public readonly bool $dryRun,
        public readonly CarbonImmutable $softDeleteBefore,
        public readonly CarbonImmutable $purgeBefore,
        public readonly int $softDeletedRecordings,
        public readonly int $softDeletedTranscripts,
        public readonly int $purgedRecordings,
        public readonly int $purgedTranscripts,
    ) {
    }
}
