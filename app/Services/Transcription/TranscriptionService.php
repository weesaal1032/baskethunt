<?php

namespace App\Services\Transcription;

use App\Models\Recording;
use App\Services\Settings\SettingsService;
use App\Services\Storage\StorageService;
use App\Services\Transcription\Drivers\MockTranscriptionDriver;
use App\Services\Transcription\Drivers\TranscriptionDriver;
use App\Services\Transcription\Drivers\WhisperApiDriver;
use App\Services\Transcription\Drivers\WhisperCliDriver;
use RuntimeException;

class TranscriptionService
{
    /**
     * @var array<string, TranscriptionDriver>
     */
    private array $drivers = [];

    public function __construct(
        private readonly StorageService $storage,
        private readonly SettingsService $settings
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function transcribe(Recording $recording, string $engine, array $options = []): TranscriptionResult
    {
        $engine = $engine !== '' ? $engine : (string) ($this->settings->get('transcription.engine') ?? 'whisper_api');
        $language = (string) ($options['language'] ?? $this->settings->get('transcription.language') ?? config('callhub.transcription.language', 'en'));

        $tempPath = $this->copyRecordingToLocal($recording);

        try {
            $driver = $this->driver($engine);
            $driverOptions = array_merge($this->driverOptions($engine, $language), $options, [
                'language' => $language,
            ]);

            return $driver->transcribe($tempPath, $driverOptions);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function driver(string $engine): TranscriptionDriver
    {
        if (! isset($this->drivers[$engine])) {
            $this->drivers[$engine] = match ($engine) {
                'whisper_api' => new WhisperApiDriver(config('transcription.drivers.whisper_api', [])),
                'whisper_cli', 'whisper_local' => new WhisperCliDriver(config('transcription.drivers.whisper_cli', [])),
                'mock' => new MockTranscriptionDriver(config('transcription.drivers.mock', [])),
                default => throw new RuntimeException(sprintf('Unsupported transcription engine [%s].', $engine)),
            };
        }

        return $this->drivers[$engine];
    }

    private function copyRecordingToLocal(Recording $recording): string
    {
        $path = $recording->local_path;

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Recording is missing a stored path for transcription.');
        }

        $stream = $this->storage->streamRecording($path, $recording->storage_backend);
        $tempPath = tempnam(sys_get_temp_dir(), 'callhub-transcribe-');

        if ($tempPath === false) {
            throw new RuntimeException('Unable to allocate temporary transcription buffer.');
        }

        $handle = fopen($tempPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary transcription file for writing.');
        }

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1048576);

                if ($chunk === false) {
                    throw new RuntimeException('Error reading recording stream for transcription.');
                }

                if ($chunk === '') {
                    break;
                }

                if (fwrite($handle, $chunk) === false) {
                    throw new RuntimeException('Unable to write transcription buffer.');
                }
            }
        } finally {
            fclose($handle);

            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        return $tempPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function driverOptions(string $engine, string $language): array
    {
        return match ($engine) {
            'whisper_api' => [
                'api_key' => $this->settings->get('transcription.api_key'),
                'timeout' => (int) ($this->settings->get('transcription.api_timeout') ?? config('callhub.transcription.api_timeout', 30)),
                'language' => $language,
            ],
            'whisper_cli', 'whisper_local' => [
                'binary' => $this->settings->get('transcription.cli_path') ?? config('callhub.transcription.cli_path'),
                'model' => $this->settings->get('transcription.cli_model') ?? config('callhub.transcription.cli_model', 'base.en'),
                'threads' => (int) ($this->settings->get('transcription.cli_threads') ?? config('callhub.transcription.cli_threads', 4)),
                'timeout' => (int) ($this->settings->get('transcription.cli_timeout') ?? config('callhub.transcription.cli_timeout', 600)),
                'language' => $language,
            ],
            default => [],
        };
    }
}
