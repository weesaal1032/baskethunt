<?php

namespace App\Services\Health;

final class E2eHealthCheckStep
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $passed,
        public readonly string $message,
        public readonly array $details = []
    ) {
    }
}
