<?php

namespace App\Jobs;

use App\Models\Job as DomainJob;
use App\Models\Recording;
use App\Services\Notifications\NotificationService;
use App\Services\Settings\SettingsService;
use App\Services\Storage\StorageService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DownloadRecordingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const MAX_ATTEMPTS = 5;

    /**
     * @var array<string, string>
     */
    private const CONTENT_TYPE_EXTENSION_MAP = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/aac' => 'aac',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'webm',
        'audio/x-ms-wma' => 'wma',
    ];

    public function __construct(public readonly int $recordingId, public readonly ?int $jobId = null)
    {
        $this->queue = 'recordings';
    }

    public function handle(StorageService $storage, SettingsService $settings, NotificationService $notifications): void
    {
        $domainJob = $this->locateDomainJob();
        $this->markRunning($domainJob);

        $recording = Recording::query()->with('call')->find($this->recordingId);

        if ($recording === null) {
            $this->markDone($domainJob);

            return;
        }

        if ($recording->status === 'ready' && $recording->local_path !== null) {
            $this->markDone($domainJob);

            return;
        }

        $remoteUrl = $recording->remote_url;

        if ($remoteUrl === null || $remoteUrl === '') {
            $final = $this->markFailed($domainJob, new RuntimeException('Recording missing remote URL.'), false);
            $recording->status = 'failed';
            $recording->save();

            if ($final) {
                $this->notifyFailure($notifications, $recording->id, 'missing remote URL');
            }

            return;
        }

        $recording->status = 'downloading';
        $recording->save();

        $downloadPath = null;
        $transcodedPath = null;

        try {
            $download = $this->downloadToTemp($remoteUrl);
            $downloadPath = $download['path'];
            $format = $download['format'];

            if ($format !== 'mp3') {
                $transcodedPath = $this->transcodeToMp3($downloadPath);
                $format = 'mp3';
            }

            $finalPath = $transcodedPath ?? $downloadPath;
            $checksum = hash_file('sha256', $finalPath) ?: $download['checksum'];
            $bytes = filesize($finalPath) ?: $download['bytes'];
            $identifier = $recording->call?->provider_call_id ?? 'recording-'.$recording->id;

            $handle = fopen($finalPath, 'rb');

            if ($handle === false) {
                throw new RuntimeException('Unable to open final recording for storage.');
            }

            try {
                $stored = $storage->storeRecording($identifier, $handle, 'mp3', $recording->storage_backend);
            } finally {
                fclose($handle);
            }

            $waveform = $this->generateWaveform($finalPath);

            $recording->forceFill([
                'local_path' => $stored->path,
                'storage_backend' => $stored->disk,
                'format' => $format,
                'bytes' => $bytes,
                'checksum' => $checksum,
                'waveform_json' => $waveform,
                'status' => 'ready',
            ])->save();

            $this->markDone($domainJob);
            $this->enqueueTranscriptionIfEnabled($recording, $settings);
        } catch (Throwable $throwable) {
            $recording->status = 'failed';
            $recording->save();

            $final = $this->markFailed($domainJob, $throwable);

            Log::error('Recording download failed.', [
                'recording_id' => $this->recordingId,
                'job_id' => $this->jobId,
                'message' => $throwable->getMessage(),
            ]);

            if ($final) {
                $this->notifyFailure($notifications, $recording->id, $throwable->getMessage());
            }

            if ($this->job !== null) {
                $this->release($this->backoffSeconds($domainJob?->attempts ?? 1));
            }

            return;
        } finally {
            if ($downloadPath !== null && file_exists($downloadPath)) {
                @unlink($downloadPath);
            }

            if ($transcodedPath !== null && file_exists($transcodedPath)) {
                @unlink($transcodedPath);
            }
        }
    }

    private function locateDomainJob(): ?DomainJob
    {
        if ($this->jobId === null) {
            return null;
        }

        return DomainJob::query()->find($this->jobId);
    }

    private function markRunning(?DomainJob $domainJob): void
    {
        if ($domainJob === null) {
            return;
        }

        $domainJob->attempts++;
        $domainJob->status = 'running';
        $domainJob->run_at = CarbonImmutable::now();
        $domainJob->save();
    }

    private function markDone(?DomainJob $domainJob): void
    {
        if ($domainJob === null) {
            return;
        }

        $domainJob->status = 'done';
        $domainJob->last_error = null;
        $domainJob->run_at = CarbonImmutable::now();
        $domainJob->save();
    }

    private function markFailed(?DomainJob $domainJob, Throwable $throwable, bool $retryable = true): bool
    {
        if ($domainJob === null) {
            return true;
        }

        $domainJob->last_error = $throwable->getMessage();

        if (! $retryable || $domainJob->attempts >= self::MAX_ATTEMPTS) {
            $domainJob->status = 'failed';
            $domainJob->run_at = CarbonImmutable::now();
            $domainJob->save();

            return true;
        }

        $domainJob->status = 'queued';
        $domainJob->run_at = CarbonImmutable::now()->addSeconds($this->backoffSeconds($domainJob->attempts));
        $domainJob->save();

        return false;
    }

    /**
     * @return array{path: string, format: string, checksum: string, bytes: int}
     */
    private function downloadToTemp(string $remoteUrl): array
    {
        $tempDirectory = storage_path('app/tmp');

        if (! is_dir($tempDirectory) && ! mkdir($tempDirectory, 0775, true) && ! is_dir($tempDirectory)) {
            throw new RuntimeException('Unable to create temporary storage directory for downloads.');
        }

        $tempPath = tempnam($tempDirectory, 'callhub_dl_');

        if ($tempPath === false) {
            throw new RuntimeException('Failed to allocate temporary file for download.');
        }

        $handle = fopen($tempPath, 'w+b');

        if ($handle === false) {
            throw new RuntimeException('Failed to open temporary file for download.');
        }

        try {
            $response = Http::timeout(120)->withOptions(['stream' => true])->get($remoteUrl);

            if ($response->failed()) {
                throw new RuntimeException(sprintf('Remote recording responded with HTTP %d.', $response->status()));
            }

            $contentLengthHeader = $response->header('Content-Length');
            $contentLength = is_array($contentLengthHeader)
                ? (int) ($contentLengthHeader[0] ?? 0)
                : ($contentLengthHeader !== null ? (int) $contentLengthHeader : null);

            $contentTypeHeader = $response->header('Content-Type');
            $contentType = is_array($contentTypeHeader) ? ($contentTypeHeader[0] ?? null) : $contentTypeHeader;

            $stream = $response->toPsrResponse()->getBody();
            $hash = hash_init('sha256');
            $bytes = 0;

            while (! $stream->eof()) {
                $chunk = $stream->read(1024 * 1024);

                if ($chunk === '' || $chunk === false) {
                    usleep(50000);

                    continue;
                }

                $bytes += strlen($chunk);
                $written = fwrite($handle, $chunk);

                if ($written === false) {
                    throw new RuntimeException('Failed to write downloaded chunk to temporary file.');
                }

                hash_update($hash, $chunk);
            }

            $stream->close();
            fflush($handle);

            if ($contentLength !== null && $contentLength > 0 && $bytes !== $contentLength) {
                throw new RuntimeException(sprintf(
                    'Content length mismatch. Expected %d bytes but received %d bytes.',
                    $contentLength,
                    $bytes
                ));
            }

            return [
                'path' => $tempPath,
                'format' => $this->determineFormat($remoteUrl, $contentType),
                'checksum' => hash_final($hash),
                'bytes' => $bytes,
            ];
        } catch (Throwable $throwable) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            @unlink($tempPath);

            throw $throwable;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    private function determineFormat(string $remoteUrl, ?string $contentType): string
    {
        if ($contentType !== null) {
            $normalised = strtolower(trim($contentType));

            if (isset(self::CONTENT_TYPE_EXTENSION_MAP[$normalised])) {
                return self::CONTENT_TYPE_EXTENSION_MAP[$normalised];
            }
        }

        $path = parse_url($remoteUrl, PHP_URL_PATH) ?? '';
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (is_string($extension) && $extension !== '') {
            return strtolower($extension);
        }

        return 'mp3';
    }

    private function transcodeToMp3(string $sourcePath): string
    {
        $targetPath = $sourcePath.'.mp3';
        $process = new Process([
            $this->ffmpegBinary(),
            '-y',
            '-i',
            $sourcePath,
            '-vn',
            '-acodec',
            'libmp3lame',
            '-ar',
            '44100',
            $targetPath,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('FFmpeg transcode failed: '.$process->getErrorOutput());
        }

        return $targetPath;
    }

    /**
     * @return array<int, float>
     */
    private function generateWaveform(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $fileSize = filesize($path);

        if (! is_int($fileSize) || $fileSize <= 0) {
            fclose($handle);

            return [];
        }

        $segments = 120;
        $chunkSize = max(1, (int) floor($fileSize / $segments));
        $waveform = [];

        for ($index = 0; $index < $segments; $index++) {
            $data = fread($handle, $chunkSize);

            if ($data === false || $data === '') {
                break;
            }

            $max = 0;
            $length = strlen($data);

            for ($offset = 0; $offset < $length; $offset++) {
                $max = max($max, abs(ord($data[$offset]) - 128));
            }

            $waveform[] = round(min(1, $max / 128), 3);
        }

        fclose($handle);

        return $waveform;
    }

    private function enqueueTranscriptionIfEnabled(Recording $recording, SettingsService $settings): void
    {
        $engine = (string) ($settings->get('transcription.engine') ?? '');

        if ($engine === '' || $engine === 'disabled') {
            return;
        }

        $exists = DomainJob::query()
            ->where('type', 'transcribe')
            ->whereJsonContains('payload->recording_id', $recording->id)
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($exists) {
            return;
        }

        $job = new DomainJob();
        $job->type = 'transcribe';
        $language = (string) ($settings->get('transcription.language') ?? 'en');

        $job->payload = [
            'recording_id' => $recording->id,
            'engine' => $engine,
            'language' => $language,
        ];
        $job->status = 'queued';
        $job->attempts = 0;
        $job->run_at = CarbonImmutable::now();
        $job->save();
    }

    private function ffmpegBinary(): string
    {
        $configured = config('callhub.media.ffmpeg_binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $env = env('FFMPEG_BINARY');

        if (is_string($env) && $env !== '') {
            return $env;
        }

        return 'ffmpeg';
    }

    private function backoffSeconds(int $attempt): int
    {
        $attempt = max(1, $attempt);

        return min(900, 5 * (2 ** ($attempt - 1)));
    }

    private function notifyFailure(NotificationService $notifications, int $recordingId, string $reason): void
    {
        $notifications->sendAlert(
            'CallHub Recording Download Failed',
            sprintf('Recording %d could not be downloaded: %s', $recordingId, $reason),
            ['recording_id' => $recordingId],
            'notifications.download.last_failure_'.$recordingId,
            30
        );
    }
}
