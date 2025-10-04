<?php

namespace Tests\Feature\Console;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_run_updates_last_run_setting(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $this->assertNull($settings->get('system.jobs.last_ran_at'));

        Artisan::call('jobs:run', ['--once' => true, '--max' => 0]);

        $this->assertNotNull($settings->get('system.jobs.last_ran_at'));
    }
}
