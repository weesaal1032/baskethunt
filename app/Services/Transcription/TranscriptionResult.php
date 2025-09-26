<?php

namespace App\Services\Transcription;

/**
 * @phpstan-type Segment array{start: float, end: float, text: string}
 */
class TranscriptionResult
{
    /**
     * @param array<int, array{start: float, end: float, text: string}> $segments
     */
    public function __construct(
        public readonly string $text,
        public readonly array $segments,
        public readonly ?float $confidence,
        public readonly string $language
    ) {
    }
}
