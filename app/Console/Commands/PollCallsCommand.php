<?php

namespace App\Console\Commands;

use App\Services\Calls\CallIngestionResult;
use App\Services\Calls\CallIngestionService;
use Illuminate\Console\Command;
use Throwable;

class PollCallsCommand extends Command
{
    protected $signature = 'poll:calls';

    protected $description = 'Poll the configured telephony provider for recent calls.';

    public function __construct(private readonly CallIngestionService $ingestionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->ingestionService->poll();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Call polling failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->renderSummary($result);

        return self::SUCCESS;
    }

    private function renderSummary(CallIngestionResult $result): void
    {
        $this->info(sprintf(
            'Calls processed: %d (created: %d, updated: %d). Downloads queued: %d.',
            $result->processed,
            $result->created,
            $result->updated,
            $result->downloadsQueued,
        ));

        if ($result->nextCursor !== null) {
            $this->line('Next cursor: '.$result->nextCursor);
        }
    }
}
