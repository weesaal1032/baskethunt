<?php

namespace App\Console\Commands;

use App\Services\Privacy\RetentionService;
use Illuminate\Console\Command;

final class EnforceRetentionCommand extends Command
{
    protected $signature = 'privacy:enforce-retention {--dry-run : Simulate retention without deleting data}';

    protected $description = 'Apply the configured retention policy to recordings and transcripts.';

    public function __construct(private readonly RetentionService $retention)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $outcome = $this->retention->enforce($dryRun);

        $this->info(sprintf('Retention window: soft delete before %s, purge before %s',
            $outcome->softDeleteBefore->toDateTimeString(),
            $outcome->purgeBefore->toDateTimeString()
        ));

        $this->line(sprintf('Soft deleted recordings: %d', $outcome->softDeletedRecordings));
        $this->line(sprintf('Soft deleted transcripts: %d', $outcome->softDeletedTranscripts));
        $this->line(sprintf('Purged recordings: %d', $outcome->purgedRecordings));
        $this->line(sprintf('Purged transcripts: %d', $outcome->purgedTranscripts));

        if ($dryRun) {
            $this->comment('Dry run completed. No changes were persisted.');
        }

        return self::SUCCESS;
    }
}
