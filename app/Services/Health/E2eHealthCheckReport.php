<?php

namespace App\Services\Health;

use App\Models\Call;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Transcript;

final class E2eHealthCheckReport
{
    /**
     * @param list<E2eHealthCheckStep> $steps
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        public readonly bool $successful,
        public readonly array $steps,
        public readonly ?Call $call,
        public readonly ?Recording $recording,
        public readonly ?Transcript $transcript,
        public readonly ?QaScore $qaScore,
        public readonly array $metrics,
    ) {
    }
}
