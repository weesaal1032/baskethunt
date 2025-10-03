<?php

namespace App\Services\Privacy\Data;

final class CallDeletionOutcome
{
    public function __construct(
        public readonly int $callId,
        public readonly ?string $providerCallId,
        public readonly bool $dryRun,
        public readonly int $recordingsDeleted,
        public readonly int $transcriptsDeleted,
        public readonly int $storageObjectsDeleted,
    ) {
    }
}
