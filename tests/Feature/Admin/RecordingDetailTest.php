<?php

namespace Tests\Feature\Admin;

use App\Models\Call;
use App\Models\Provider;
use App\Models\Recording;
use App\Models\Transcript;
use App\Models\User;
use App\Services\Storage\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class RecordingDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_recording_detail_page(): void
    {
        $viewer = User::factory()->create(['role' => 'qa']);
        $provider = Provider::factory()->create(['name' => 'DemoTel']);
        $call = Call::query()->create([
            'provider_call_id' => 'call-detail',
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'from_number' => '+15551230000',
            'to_number' => '+15557654321',
            'agent_id' => $viewer->id,
            'started_at' => Carbon::parse('2024-05-11 10:00:00'),
            'ended_at' => Carbon::parse('2024-05-11 10:07:00'),
            'duration_sec' => 420,
            'disposition' => 'completed',
            'queue' => 'sales',
            'metadata' => ['case' => 'A1'],
        ]);

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'local_path' => 'recordings/2024/05/call-detail.mp3',
            'storage_backend' => 'local',
            'status' => 'ready',
            'format' => 'mp3',
            'waveform_json' => [0.1, 0.5, 0.3],
        ]);

        Transcript::query()->create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'Hello world',
            'segments' => [
                ['start' => 0.0, 'end' => 3.2, 'text' => 'Hello there'],
                ['start' => 3.2, 'end' => 7.5, 'text' => 'Welcome to the demo call'],
            ],
            'status' => 'ready',
        ]);

        $response = $this->actingAs($viewer)->get(route('admin.recordings.show', $recording));

        $response->assertOk();
        $response->assertSeeText('Call review');
        $response->assertSeeText('Hello there');
        $response->assertSeeText('Welcome to the demo call');
        $response->assertSee('/admin/recordings/'.$recording->id.'/audio');
    }

    public function test_signed_audio_route_streams_local_recording(): void
    {
        $viewer = User::factory()->create(['role' => 'qa']);
        Storage::fake('local');

        $call = $this->createCall();

        $path = 'recordings/test.mp3';
        Storage::disk('local')->put($path, 'audio-bytes');

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'local_path' => $path,
            'storage_backend' => 'local',
            'format' => 'mp3',
            'status' => 'ready',
        ]);

        $signedUrl = URL::temporarySignedRoute('admin.recordings.audio', now()->addMinutes(5), [
            'recording' => $recording->id,
        ]);

        $response = $this->actingAs($viewer)->get($signedUrl);

        $response->assertOk();
        $response->assertHeader('content-type', 'audio/mpeg');
        $response->assertHeader('accept-ranges', 'bytes');
        $this->assertSame('audio-bytes', $response->streamedContent());
    }

    public function test_signed_audio_route_redirects_for_s3_recording(): void
    {
        $viewer = User::factory()->create(['role' => 'qa']);
        $call = $this->createCall();
        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'local_path' => 'recordings/s3-file.mp3',
            'storage_backend' => 's3',
            'format' => 'mp3',
            'status' => 'ready',
        ]);

        $expectedUrl = 'https://signed.example/audio.mp3';

        $this->mock(StorageService::class, function ($mock) use ($expectedUrl): void {
            $mock->shouldReceive('temporaryUrl')
                ->once()
                ->with('recordings/s3-file.mp3', Mockery::type(\DateTimeInterface::class), 's3')
                ->andReturn($expectedUrl);
        });

        $signedUrl = URL::temporarySignedRoute('admin.recordings.audio', now()->addMinutes(5), [
            'recording' => $recording->id,
        ]);

        $response = $this->actingAs($viewer)->get($signedUrl);

        $response->assertRedirect($expectedUrl);
}

    private function createCall(?int $agentId = null): Call
    {
        $provider = Provider::factory()->create();

        return Call::query()->create([
            'provider_call_id' => 'call-'.uniqid('', true),
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'from_number' => '+15550000000',
            'to_number' => '+15551112222',
            'agent_id' => $agentId,
            'started_at' => Carbon::now()->subMinutes(10),
            'ended_at' => Carbon::now()->subMinutes(5),
            'duration_sec' => 300,
            'disposition' => 'completed',
            'queue' => 'support',
            'metadata' => ['auto' => true],
        ]);
    }
}
