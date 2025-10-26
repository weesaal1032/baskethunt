<?php

namespace App\Console;

use App\Services\Settings\SettingsService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (): void {
            /** @var SettingsService $settings */
            $settings = app(SettingsService::class);
            $settings->set('system.scheduler.last_ran_at', now()->toIso8601String());
        })->name('scheduler-heartbeat')->everyMinute()->withoutOverlapping();

        $schedule->command('poll:calls')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('jobs:run --once --max=10')->everyMinute()->withoutOverlapping();
        $schedule->command('privacy:enforce-retention')->dailyAt('02:15')->withoutOverlapping();
        $schedule->command('notifications:health-check')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('notifications:daily-summary')->dailyAt('07:30')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
