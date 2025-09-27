<?php

namespace Tests\Feature\Admin;

use App\Models\Call;
use App\Models\Provider;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecordingLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_recording_library(): void
    {
        $viewer = User::factory()->create(['role' => 'qa']);
        $agent = User::factory()->create(['role' => 'lead', 'name' => 'Agent Smith']);
        $provider = Provider::factory()->create();

        $call = Call::query()->create([
            'provider_call_id' => 'call-123',
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'from_number' => '+15551234567',
            'to_number' => '+15557654321',
            'agent_id' => $agent->id,
            'started_at' => Carbon::parse('2024-05-10 12:00:00'),
            'ended_at' => Carbon::parse('2024-05-10 12:05:00'),
            'duration_sec' => 300,
            'disposition' => 'completed',
            'queue' => 'sales',
        ]);

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'storage_backend' => 'local',
            'status' => 'ready',
            'format' => 'mp3',
            'bytes' => 1024,
            'checksum' => 'abc123',
        ]);

        Transcript::query()->create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'Hello world',
            'status' => 'ready',
        ]);

        QaScore::query()->create([
            'call_id' => $call->id,
            'scored_by' => $viewer->id,
            'rubric' => ['opening' => 5],
            'total_score' => 95,
            'comments' => 'Great call',
        ]);

        $response = $this->actingAs($viewer)->get(route('admin.recordings.index', [
            'agent' => $agent->id,
            'has_transcript' => 'with',
            'has_qa_score' => 'with',
        ]));

        $response->assertOk();
        $response->assertSeeText('Recording Library');
        $response->assertSeeText('Agent Smith');
        $response->assertSee('Ready');
        $response->assertSee('Scored');
        $response->assertSee('+••••4321');
    }

    public function test_filters_limit_results_to_selected_agent(): void
    {
        $viewer = User::factory()->create(['role' => 'lead']);
        $provider = Provider::factory()->create();
        $firstAgent = User::factory()->create(['role' => 'qa', 'name' => 'First Agent']);
        $secondAgent = User::factory()->create(['role' => 'qa', 'name' => 'Second Agent']);

        $this->createRecording($provider, $firstAgent, 'call-1');
        $this->createRecording($provider, $secondAgent, 'call-2');

        $response = $this->actingAs($viewer)->get(route('admin.recordings.index', [
            'agent' => $firstAgent->id,
        ]));

        $response->assertOk();
        $response->assertSeeText('First Agent');
        $response->assertDontSeeText('Second Agent');
    }

    public function test_csv_export_respects_filters_and_privacy_mask(): void
    {
        Settings::query()->create([
            'key' => 'privacy.pii_masking',
            'value' => '1',
        ]);

        $viewer = User::factory()->create(['role' => 'readonly']);
        $provider = Provider::factory()->create();
        $agent = User::factory()->create(['role' => 'qa']);

        $recording = $this->createRecording($provider, $agent, 'call-csv');
        Transcript::query()->create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'Transcript body',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($viewer)->get(route('admin.recordings.export', [
            'has_transcript' => 'with',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Recording ID', $csv);
        $this->assertStringContainsString('••••4321', $csv);
        $this->assertStringNotContainsString('+15557654321', $csv);
    }

    private function createRecording(Provider $provider, User $agent, string $providerCallId): Recording
    {
        $call = Call::query()->create([
            'provider_call_id' => $providerCallId,
            'provider_id' => $provider->id,
            'direction' => 'outbound',
            'from_number' => '+15559876543',
            'to_number' => '+15557654321',
            'agent_id' => $agent->id,
            'started_at' => Carbon::parse('2024-05-11 09:00:00'),
            'ended_at' => Carbon::parse('2024-05-11 09:05:00'),
            'duration_sec' => 300,
            'disposition' => 'completed',
            'queue' => 'support',
        ]);

        return Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'storage_backend' => 'local',
            'status' => 'ready',
            'format' => 'mp3',
            'bytes' => 1024,
            'checksum' => 'checksum-' . $providerCallId,
        ]);
    }
}
