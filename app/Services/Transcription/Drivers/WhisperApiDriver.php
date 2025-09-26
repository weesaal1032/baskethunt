<?php

namespace App\Services\Transcription\Drivers;

use App\Services\Transcription\TranscriptionResult;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhisperApiDriver implements TranscriptionDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function transcribe(string $filePath, array $options = []): TranscriptionResult
    {
        $endpoint = (string) ($this->config['endpoint'] ?? '');

        if ($endpoint === '') {
            throw new RuntimeException('Whisper API endpoint is not configured.');
        }

        $apiKey = (string) ($options['api_key'] ?? $this->config['api_key'] ?? '');

        if ($apiKey === '') {
            throw new RuntimeException('Whisper API key is not configured.');
        }

        $timeout = (int) ($options['timeout'] ?? $this->config['timeout'] ?? 30);
        $language = (string) ($options['language'] ?? 'en');
        $model = (string) ($options['model'] ?? 'whisper-1');

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open recording at [%s] for transcription.', $filePath));
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->asMultipart()
                ->attach('file', $handle, basename($filePath))
                ->post($endpoint, [
                    'model' => $model,
                    'language' => $language,
                    'response_format' => 'verbose_json',
                ]);
        } finally {
            fclose($handle);
        }

        if ($response->failed()) {
            throw new RuntimeException(sprintf('Whisper API request failed with status %s', $response->status()));
        }

        $payload = $response->json();

        if (! \is_array($payload)) {
            throw new RuntimeException('Whisper API response was not valid JSON.');
        }

        $text = (string) ($payload['text'] ?? '');
        $segments = [];
        $confidenceValues = [];

        foreach (Arr::get($payload, 'segments', []) as $segment) {
            if (! \is_array($segment)) {
                continue;
            }

            $start = (float) ($segment['start'] ?? 0.0);
            $end = (float) ($segment['end'] ?? $start);
            $content = (string) ($segment['text'] ?? '');

            $segments[] = [
                'start' => $start,
                'end' => $end,
                'text' => $content,
            ];

            if (isset($segment['confidence'])) {
                $confidenceValues[] = (float) $segment['confidence'];
            }
        }

        $confidence = null;

        if ($confidenceValues !== []) {
            $confidence = array_sum($confidenceValues) / count($confidenceValues);
        }

        return new TranscriptionResult($text, $segments, $confidence, $language);
    }
}
