<?php

namespace Tests\Feature\Console;

use App\Models\Call;
use App\Models\Provider;
use App\Models\Recording;
use App\Models\User;
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
        $agent = User::factory()->create(['email' => 'alice.agent@example.com']);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->setMany([
            'telephony.provider.mapping' => [
                'provider_call_id' => 'id',
                'from_number' => 'attributes.from',
                'to_number' => 'attributes.to',
                'started_at' => 'attributes.started_at',
                'ended_at' => 'attributes.ended_at',
                'duration' => 'attributes.durationSeconds',
                'status' => 'attributes.status',
                'direction' => 'attributes.direction',
                'recording_url' => 'relationships.recording.data.url',
                'queue' => 'attributes.queueId',
                'agent_email' => 'attributes.agent.email',
                'agent_name' => 'attributes.agent.name',
            ],
            'telephony.provider.poll_window_days' => 5,
            'telephony.provider.provider_id' => $provider->id,
        ]);

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/providers/telephony_calls.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $callPayload = $fixture['data'][0];

        $mockClient = $this->createMock(TelephonyClientInterface::class);
        $mockClient->method('listCalls')->willReturn(
            new TelephonyCallPage(collect([$callPayload]), null)
        );

        $this->app->instance(TelephonyClientInterface::class, $mockClient);

        Artisan::call('poll:calls');

        $this->assertDatabaseCount('calls', 1);
        $call = Call::query()->firstOrFail();
        $this->assertSame('call-001', $call->provider_call_id);
        $this->assertSame('outbound', $call->direction);
        $this->assertSame('completed', $call->disposition);
        $this->assertSame('sales', $call->queue);
        $this->assertSame($agent->id, $call->agent_id);

        $recording = Recording::query()->firstOrFail();
        $this->assertSame('https://telephony.test/recordings/call-001.mp3', $recording->remote_url);
        $this->assertSame('pending', $recording->status);

        $this->assertSame('alice.agent@example.com', data_get($call->metadata, '__callhub_meta.mapped_fields.agent_email'));
        $this->assertSame('Alice Agent', data_get($call->metadata, '__callhub_meta.mapped_fields.agent_name'));

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
