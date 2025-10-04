<?php

namespace Tests\Feature\Console;

use App\Models\Call;
use App\Models\Provider;
use App\Models\Recording;
use App\Services\Providers\TelephonyClientInterface;
use App\Services\Providers\ValueObjects\TelephonyCallPage;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PollCallsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_command_ingests_calls_and_queues_downloads(): void
    {
        config(['queue.default' => 'database']);

        $provider = Provider::factory()->create();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->setMany([
            'telephony.provider.mapping' => [
                'provider_call_id' => 'id',
                'from_number' => 'from',
                'to_number' => 'to',
                'started_at' => 'started_at',
                'ended_at' => 'ended_at',
                'duration' => 'duration',
                'status' => 'status',
                'recording_url' => 'recording.url',
                'direction' => 'direction',
            ],
            'telephony.provider.poll_window_days' => 5,
            'telephony.provider.provider_id' => $provider->id,
        ]);

        $callPayload = [
            'id' => 'call-123',
            'from' => '+15550000001',
            'to' => '+15550000099',
            'started_at' => '2024-05-10T10:00:00Z',
            'ended_at' => '2024-05-10T10:05:00Z',
            'duration' => 300,
            'status' => 'completed',
            'direction' => 'outbound',
            'recording' => [
                'url' => 'https://example.com/recording.mp3',
            ],
        ];

        $mockClient = $this->createMock(TelephonyClientInterface::class);
        $mockClient->method('listCalls')->willReturn(
            new TelephonyCallPage(collect([$callPayload]), null)
        );

        $this->app->instance(TelephonyClientInterface::class, $mockClient);

        Artisan::call('poll:calls');

        $this->assertDatabaseCount('calls', 1);
        $call = Call::query()->firstOrFail();
        $this->assertSame('call-123', $call->provider_call_id);
        $this->assertSame('outbound', $call->direction);
        $this->assertSame('completed', $call->disposition);

        $recording = Recording::query()->firstOrFail();
        $this->assertSame('https://example.com/recording.mp3', $recording->remote_url);
        $this->assertSame('pending', $recording->status);

        $this->assertDatabaseHas('jobs', [
            'type' => 'download',
            'status' => 'queued',
        ]);

        $queuedJob = DB::table('queue_jobs')->first();
        $this->assertNotNull($queuedJob);
        $this->assertSame('recordings', $queuedJob->queue);

        $health = $this->get('/health/status')->json();
        $this->assertArrayHasKey('telephony_last_poll_at', $health);
        $this->assertArrayHasKey('scheduler_last_ran_at', $health);
        $this->assertArrayHasKey('jobs_runner_last_ran_at', $health);
        $this->assertNotNull($health['telephony_last_poll_at']);
    }
}
