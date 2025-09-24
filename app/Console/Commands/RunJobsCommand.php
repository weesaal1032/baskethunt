<?php

namespace App\Console\Commands;

use App\Jobs\DownloadRecordingJob;
use App\Models\Job as DomainJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunJobsCommand extends Command
{
    protected $signature = 'jobs:run {--once : Process only a single polling cycle} {--sleep=5 : Seconds to sleep when no jobs are available} {--max=0 : Maximum jobs to process in this invocation (0 = unlimited)}';

    protected $description = 'Process domain jobs such as recording downloads without relying on a long-running worker.';

    public function handle(): int
    {
        $once = (bool) $this->option('once');
        $sleep = max(1, (int) $this->option('sleep'));
        $max = max(0, (int) $this->option('max'));
        $processed = 0;

        while (true) {
            $job = $this->reserveJob();

            if ($job === null) {
                if ($once || ($max > 0 && $processed >= $max)) {
                    break;
                }

                sleep($sleep);

                continue;
            }

            $payload = $job->payload ?? [];
            $recordingId = (int) ($payload['recording_id'] ?? 0);

            if ($recordingId <= 0) {
                $job->status = 'failed';
                $job->last_error = 'Invalid recording identifier in payload.';
                $job->run_at = CarbonImmutable::now();
                $job->save();
                $this->warn(sprintf('Job %d missing recording_id, marking as failed.', $job->id));
            } else {
                try {
                    DownloadRecordingJob::dispatchSync($recordingId, $job->id);
                    ++$processed;
                } catch (Throwable $throwable) {
                    $job->refresh();

                    if ($job->status === 'running') {
                        $job->status = 'failed';
                        $job->last_error = $throwable->getMessage();
                        $job->run_at = CarbonImmutable::now();
                        $job->save();
                    }

                    Log::error('Domain job execution failed.', [
                        'job_id' => $job->id,
                        'message' => $throwable->getMessage(),
                    ]);
                }
            }

            if ($once) {
                break;
            }

            if ($max > 0 && $processed >= $max) {
                break;
            }
        }

        return self::SUCCESS;
    }

    private function reserveJob(): ?DomainJob
    {
        return DB::transaction(function () {
            $job = DomainJob::query()
                ->where('type', 'download')
                ->where('status', 'queued')
                ->where(function ($query) {
                    $query->whereNull('run_at')->orWhere('run_at', '<=', CarbonImmutable::now());
                })
                ->orderBy('run_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($job !== null) {
                $job->status = 'running';
                $job->run_at = CarbonImmutable::now();
                $job->save();
            }

            return $job;
        }, 3);
    }
}
