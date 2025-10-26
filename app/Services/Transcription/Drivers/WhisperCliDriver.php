<?php

namespace App\Services\Transcription\Drivers;

use App\Services\Transcription\TranscriptionResult;
use Illuminate\Support\Arr;
use RuntimeException;
use Symfony\Component\Process\Process;

class WhisperCliDriver implements TranscriptionDriver
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
        $binary = (string) ($options['binary'] ?? $this->config['binary'] ?? '');

        if ($binary === '') {
            throw new RuntimeException('whisper.cpp binary path is not configured.');
        }

        $model = (string) ($options['model'] ?? $this->config['model'] ?? 'base.en');
        $threads = max(1, (int) ($options['threads'] ?? $this->config['threads'] ?? 4));
        $timeout = (int) ($options['timeout'] ?? $this->config['timeout'] ?? 600);
        $language = (string) ($options['language'] ?? 'en');

        $outputDir = $options['output_dir'] ?? (sys_get_temp_dir().DIRECTORY_SEPARATOR.'whisper-'.uniqid());

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0775, true) && ! is_dir($outputDir)) {
            throw new RuntimeException(sprintf('Unable to create whisper.cpp output directory [%s].', $outputDir));
        }

        $command = [
            $binary,
            $filePath,
            '--model', $model,
            '--language', $language,
            '--output-json',
            '--output-format', 'json',
            '--output-dir', $outputDir,
            '--threads', (string) $threads,
        ];

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput());

            throw new RuntimeException(sprintf('whisper.cpp exited with error: %s', $errorOutput));
        }

        $baseName = pathinfo($filePath, PATHINFO_FILENAME);
        $jsonPath = $outputDir.DIRECTORY_SEPARATOR.$baseName.'.json';

        if (! file_exists($jsonPath)) {
            throw new RuntimeException(sprintf('whisper.cpp did not produce JSON output at [%s].', $jsonPath));
        }

        $contents = file_get_contents($jsonPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read whisper.cpp JSON output.');
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Failed to decode whisper.cpp JSON output: '.$exception->getMessage(), 0, $exception);
        } finally {
            @unlink($jsonPath);
            @rmdir($outputDir);
        }

        $text = (string) ($payload['text'] ?? '');
        $segments = [];

        foreach (Arr::get($payload, 'segments', []) as $segment) {
            if (! \is_array($segment)) {
                continue;
            }

            $segments[] = [
                'start' => (float) ($segment['start'] ?? 0.0),
                'end' => (float) ($segment['end'] ?? 0.0),
                'text' => (string) ($segment['text'] ?? ''),
            ];
        }

        return new TranscriptionResult($text, $segments, null, $language);
    }
}
