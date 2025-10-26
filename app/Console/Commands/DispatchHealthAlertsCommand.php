<?php

namespace App\Console\Commands;

use App\Models\Job as DomainJob;
use App\Services\Admin\AdminDashboardService;
use App\Services\Notifications\NotificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

class DispatchHealthAlertsCommand extends Command
{
    protected $signature = 'notifications:health-check';

    protected $description = 'Evaluate operational thresholds and dispatch alerts when exceeded.';

    public function handle(
        AdminDashboardService $dashboard,
        SettingsService $settings,
        NotificationService $notifications
    ): int {
        $metrics = $dashboard->metrics();

        $this->checkDiskUsage($metrics->diskUsage->usedPercent, $settings, $notifications);
        $this->checkS3Usage($metrics->storageBreakdown, $settings, $notifications);
        $this->checkTranscriptionBacklog($settings, $notifications);

        $this->info('Health thresholds evaluated.');

        return self::SUCCESS;
    }

    private function checkDiskUsage(?float $usedPercent, SettingsService $settings, NotificationService $notifications): void
    {
        if ($usedPercent === null) {
            return;
        }

        $threshold = (float) ($settings->get('storage.alert.local_percent') ?? 80);

        if ($threshold <= 0 || $usedPercent < $threshold) {
            return;
        }

        $notifications->sendAlert(
            'CallHub Disk Usage Threshold Exceeded',
            sprintf('Local storage usage is currently %.1f%% which is above the %.1f%% threshold.', $usedPercent, $threshold),
            ['threshold' => $threshold, 'used_percent' => $usedPercent],
            'notifications.health.last_disk_alert_at',
            60
        );
    }

    /**
     * @param array<int, \App\Services\Admin\Data\StorageBackendBreakdown> $breakdown
     */
    private function checkS3Usage(array $breakdown, SettingsService $settings, NotificationService $notifications): void
    {
        $thresholdGb = (int) ($settings->get('storage.alert.s3_gb') ?? 0);

        if ($thresholdGb <= 0) {
            return;
        }

        $s3 = collect($breakdown)->firstWhere(fn ($item) => $item->backend === 's3');

        if ($s3 === null) {
            return;
        }

        $gigabytes = $s3->bytes / (1024 ** 3);

        if ($gigabytes < $thresholdGb) {
            return;
        }

        $notifications->sendAlert(
            'CallHub S3 Usage Threshold Exceeded',
            sprintf('S3 recordings consume %.2f GB which is above the configured %d GB threshold.', $gigabytes, $thresholdGb),
            ['threshold_gb' => $thresholdGb, 'used_gb' => round($gigabytes, 2)],
            'notifications.health.last_s3_alert_at',
            120
        );
    }

    private function checkTranscriptionBacklog(SettingsService $settings, NotificationService $notifications): void
    {
        $queued = DomainJob::query()->where('type', 'transcribe')->where('status', 'queued')->count();
        $maxConcurrent = (int) ($settings->get('transcription.max_concurrent') ?? 2);
        $threshold = (int) ($settings->get('notifications.transcription.backlog_threshold') ?? max(10, $maxConcurrent * 4));

        if ($threshold <= 0 || $queued < $threshold) {
            return;
        }

        $notifications->sendAlert(
            'CallHub Transcription Backlog Spike',
            sprintf('There are %d queued transcription jobs which exceeds the threshold of %d.', $queued, $threshold),
            ['queued' => $queued, 'threshold' => $threshold],
            'notifications.transcription.last_backlog_alert_at',
            30
        );
    }
}
