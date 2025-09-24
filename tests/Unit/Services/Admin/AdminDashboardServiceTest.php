<?php

namespace Tests\Unit\Services\Admin;

use App\Models\Call;
use App\Models\Job;
use App\Models\Provider;
use App\Models\Recording;
use App\Models\Transcript;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\Data\DiskUsageSnapshot;
use App\Services\Admin\Data\StorageBackendBreakdown;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_collects_metrics(): void
    {
        $now = CarbonImmutable::parse('2024-05-11 12:00:00');
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        $provider = Provider::create([
            'name' => 'Acme Tel',
            'base_url' => 'https://example.com',
        ]);

        $call = Call::create([
            'provider_id' => $provider->id,
            'provider_call_id' => 'call-123',
            'direction' => 'inbound',
            'from_number' => '+18005550199',
            'to_number' => '+18005550200',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $recording = Recording::create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.com/audio.mp3',
            'storage_backend' => 'local',
            'status' => 'ready',
            'bytes' => 2048,
            'checksum' => Str::random(40),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Transcript::create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'hello world',
            'segments' => json_encode([]),
            'status' => 'ready',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Job::create([
            'type' => 'download',
            'payload' => ['recording_id' => $recording->id],
            'status' => 'failed',
            'attempts' => 3,
            'run_at' => $now,
            'last_error' => 'Example failure',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Job::create([
            'type' => 'download',
            'payload' => ['recording_id' => $recording->id],
            'status' => 'queued',
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('queue_jobs')->insert([
            'queue' => 'recordings',
            'payload' => json_encode(['job' => 'download']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now->getTimestamp(),
            'created_at' => $now->getTimestamp(),
        ]);

        DB::table('failed_jobs')->insert([
            'id' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'recordings',
            'payload' => json_encode(['job' => 'download']),
            'exception' => 'Test failure',
            'failed_at' => $now,
        ]);

        $settings = app(SettingsService::class);
        $settings->set('telephony.provider.last_polled_at', $now->subMinutes(15)->toIso8601String());

        $service = app(AdminDashboardService::class);
        $metrics = $service->metrics();

        self::assertSame(1, $metrics->callsIngested24h);
        self::assertSame(1, $metrics->recordingsReady24h);
        self::assertSame(1, $metrics->transcriptsReady24h);
        self::assertSame(1, $metrics->domainFailedJobs24h);
        self::assertSame(1, $metrics->queueFailedJobs);
        self::assertSame(1, $metrics->queueDepth);
        self::assertSame(1, $metrics->queueBacklog);
        self::assertInstanceOf(DiskUsageSnapshot::class, $metrics->diskUsage);
        self::assertCount(2, $metrics->storageBreakdown);
        self::assertContainsOnlyInstancesOf(StorageBackendBreakdown::class, $metrics->storageBreakdown);
        self::assertNotEmpty($metrics->alerts);
        self::assertNotNull($metrics->lastPollAt);
    }
}
