<?php

namespace App\Services\Transcription\Drivers;

use App\Services\Transcription\TranscriptionResult;

final class MockTranscriptionDriver implements TranscriptionDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function transcribe(string $path, array $options = []): TranscriptionResult
    {
        $language = (string) ($options['language'] ?? 'en');
        $text = (string) ($this->config['text'] ?? 'Mock transcription generated for diagnostics.');
        $confidence = $this->config['confidence'] ?? 0.95;
        $duration = $this->estimateDuration($path);

        $segments = [[
            'start' => 0.0,
            'end' => $duration,
            'text' => $text,
        ]];

        return new TranscriptionResult(
            text: $text,
            segments: $segments,
            confidence: is_numeric($confidence) ? (float) $confidence : null,
            language: $language,
        );
    }

    private function estimateDuration(string $path): float
    {
        $size = is_file($path) ? (float) filesize($path) : 0.0;

        if ($size <= 0) {
            return 1.0;
        }

        $duration = max(1.0, min($size / 1024.0, 30.0));

        return round($duration, 2);
    }
}
