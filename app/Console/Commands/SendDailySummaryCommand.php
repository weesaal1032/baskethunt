<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Transcript;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendDailySummaryCommand extends Command
{
    protected $signature = 'notifications:daily-summary';

    protected $description = 'Dispatch the daily operational summary via configured notification channels.';

    public function handle(NotificationService $notifications): int
    {
        $now = CarbonImmutable::now();
        $start = $now->subDay();

        $metrics = [
            'window_start' => $start->toDateTimeString(),
            'window_end' => $now->toDateTimeString(),
            'calls_ingested' => Call::query()->where('created_at', '>=', $start)->count(),
            'recordings_ready' => Recording::query()->where('status', 'ready')->where('updated_at', '>=', $start)->count(),
            'transcripts_ready' => Transcript::query()->where('status', 'ready')->where('updated_at', '>=', $start)->count(),
            'qa_scores_submitted' => QaScore::query()->where('created_at', '>=', $start)->count(),
        ];

        $notifications->sendDailySummary($metrics);

        $this->info('Daily summary dispatched.');

        return self::SUCCESS;
    }
}
