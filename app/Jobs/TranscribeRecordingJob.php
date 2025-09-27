<?php

namespace App\Jobs;

use App\Models\Job as DomainJob;
use App\Models\Recording;
use App\Models\Transcript;
use App\Services\Settings\SettingsService;
use App\Services\Transcription\TranscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TranscribeRecordingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        public readonly int $recordingId,
        public readonly string $engine,
        public readonly ?int $jobId = null,
        public readonly ?string $language = null
    ) {
        $this->queue = 'transcriptions';
    }

    public function handle(TranscriptionService $transcriptions, SettingsService $settings): void
    {
        $domainJob = $this->locateDomainJob();
        $this->markRunning($domainJob);

        $recording = Recording::query()->with(['call', 'transcript'])->find($this->recordingId);

        if ($recording === null) {
            $this->markDone($domainJob);

            return;
        }

        if ($recording->status !== 'ready') {
            $this->markDone($domainJob);

            return;
        }

        if (! is_string($recording->local_path) || $recording->local_path === '') {
            $this->markFailed($domainJob, new RuntimeException('Recording is missing stored audio for transcription.'), false);

            return;
        }

        if (! $this->withinDailyLimit($settings, $recording, $domainJob)) {
            return;
        }

        $language = $this->language ?? (string) ($settings->get('transcription.language') ?? 'en');
        $transcript = $recording->transcript ?? new Transcript(['recording_id' => $recording->id]);
        $transcript->engine = $this->normalizeEngine($this->engine);
        $transcript->language = $language;
        $transcript->status = 'processing';
        $transcript->save();

        try {
            $result = $transcriptions->transcribe($recording, $this->engine, ['language' => $language]);

            $transcript->fill([
                'text' => $result->text,
                'segments' => $result->segments,
                'confidence' => $result->confidence,
                'language' => $result->language,
                'status' => 'ready',
            ])->save();

            $this->markDone($domainJob);
        } catch (Throwable $throwable) {
            $transcript->status = 'failed';
            $transcript->save();

            $this->markFailed($domainJob, $throwable);

            Log::error('Transcription failed.', [
                'recording_id' => $this->recordingId,
                'job_id' => $this->jobId,
                'engine' => $this->engine,
                'message' => $throwable->getMessage(),
            ]);
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

    private function markFailed(?DomainJob $domainJob, Throwable $throwable, bool $retryable = true): void
    {
        if ($domainJob === null) {
            return;
        }

        $domainJob->last_error = $throwable->getMessage();

        if (! $retryable || $domainJob->attempts >= self::MAX_ATTEMPTS) {
            $domainJob->status = 'failed';
            $domainJob->run_at = CarbonImmutable::now();
            $domainJob->save();

            return;
        }

        $domainJob->status = 'queued';
        $domainJob->run_at = CarbonImmutable::now()->addSeconds($this->backoffSeconds($domainJob->attempts));
        $domainJob->save();
    }

    private function normalizeEngine(string $engine): string
    {
        return $engine === 'whisper_cli' ? 'whisper_local' : $engine;
    }

    private function withinDailyLimit(SettingsService $settings, Recording $recording, ?DomainJob $domainJob): bool
    {
        $limitMinutes = (int) ($settings->get('transcription.daily_limit_minutes') ?? 0);

        if ($limitMinutes <= 0) {
            return true;
        }

        $durationSeconds = (int) ($recording->call?->duration_sec ?? 0);

        if ($durationSeconds <= 0) {
            $durationSeconds = (int) round(($recording->bytes ?? 0) / 16000);
        }

        $timezone = config('app.timezone', 'UTC');
        $startOfDay = CarbonImmutable::now($timezone)->startOfDay()->setTimezone('UTC');
        $usedSeconds = Transcript::query()
            ->where('created_at', '>=', $startOfDay)
            ->whereIn('status', ['ready', 'processing', 'pending'])
            ->with(['recording.call:id,duration_sec'])
            ->get()
            ->sum(static function (Transcript $transcript): int {
                return (int) ($transcript->recording?->call?->duration_sec ?? 0);
            });

        $totalMinutes = ($usedSeconds + $durationSeconds) / 60;

        if ($totalMinutes <= $limitMinutes) {
            return true;
        }

        if ($domainJob !== null) {
            $nextWindow = CarbonImmutable::now($timezone)->addDay()->startOfDay()->setTimezone('UTC');
            $domainJob->status = 'queued';
            $domainJob->run_at = $nextWindow;
            $domainJob->save();
        }

        return false;
    }

    private function backoffSeconds(int $attempt): int
    {
        $attempt = max(1, $attempt);

        return min(3600, 5 * (2 ** ($attempt - 1)));
    }
}
