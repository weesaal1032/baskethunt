<?php

namespace App\Console\Commands;

use App\Services\Privacy\RetentionService;
use Illuminate\Console\Command;

final class DeleteCallDataCommand extends Command
{
    protected $signature = 'privacy:delete-call {callId : The database identifier of the call to purge} {--dry-run : Simulate the deletion without removing data}';

    protected $description = 'Delete a single call and associated assets in response to a privacy request.';

    public function __construct(private readonly RetentionService $retention)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $callId = (int) $this->argument('callId');
        $dryRun = (bool) $this->option('dry-run');

        $outcome = $this->retention->deleteCall($callId, $dryRun);

        if ($outcome === null) {
            $this->error(sprintf('Call [%d] was not found.', $callId));

            return self::FAILURE;
        }

        $this->line(sprintf('Call %d (provider id: %s)', $outcome->callId, $outcome->providerCallId ?? 'n/a'));
        $this->line(sprintf('Recordings processed: %d', $outcome->recordingsDeleted));
        $this->line(sprintf('Transcripts processed: %d', $outcome->transcriptsDeleted));
        $this->line(sprintf('Storage objects removed: %d', $outcome->storageObjectsDeleted));

        if ($dryRun) {
            $this->comment('Dry run complete. No data was deleted.');
        } else {
            $this->info('Deletion request completed successfully.');
        }

        return self::SUCCESS;
    }
}
