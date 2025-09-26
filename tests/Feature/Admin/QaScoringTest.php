<?php

namespace Tests\Feature\Admin;

use App\Models\Call;
use App\Models\Provider;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QaScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_user_can_save_draft_score(): void
    {
        $qa = User::factory()->create(['role' => 'qa']);
        $agent = User::factory()->create(['role' => 'lead']);
        $recording = $this->createRecording($agent);

        $payload = [
            'responses' => [
                'q_greeting_open' => true,
                'q_greeting_tone' => 4.5,
                'q_resolution_process' => false,
                'q_closure_next_steps' => 3,
            ],
            'comment' => 'Strong rapport but missed process step.',
            'tags' => ['coaching', 'follow-up'],
            'passed' => false,
            'version' => 1,
            'rubric_version' => 1,
        ];

        $response = $this->actingAs($qa)->postJson(route('admin.recordings.qa.score', $recording), $payload);

        $response->assertOk();
        $response->assertJsonPath('score.status', 'draft');
        $response->assertJsonPath('score.version', 1);
        $response->assertJsonPath('next_version', 1);

        $this->assertDatabaseHas('qa_scores', [
            'call_id' => $recording->call_id,
            'status' => 'draft',
            'version' => 1,
            'scored_by' => $qa->id,
        ]);
    }

    public function test_submit_requires_matching_rubric_version(): void
    {
        $qa = User::factory()->create(['role' => 'qa']);
        $recording = $this->createRecording();

        $response = $this->actingAs($qa)->postJson(route('admin.recordings.qa.score', $recording), [
            'responses' => [],
            'comment' => null,
            'tags' => [],
            'passed' => false,
            'version' => 1,
            'rubric_version' => 999,
            'finalize' => true,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('rubric_version', 1);
    }

    public function test_submit_finalizes_score_and_returns_history(): void
    {
        $qa = User::factory()->create(['role' => 'qa']);
        $recording = $this->createRecording();

        $response = $this->actingAs($qa)->postJson(route('admin.recordings.qa.score', $recording), [
            'responses' => [
                'q_greeting_open' => true,
                'q_greeting_verification' => true,
                'q_resolution_accuracy' => 5,
            ],
            'comment' => 'Excellent execution.',
            'tags' => ['gold'],
            'passed' => true,
            'version' => 1,
            'rubric_version' => 1,
            'finalize' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('score.status', 'submitted');
        $response->assertJsonPath('score.passed', true);
        $response->assertJsonPath('history.0.status', 'submitted');
        $response->assertJsonPath('history.0.version', 1);
        $response->assertJsonPath('pass_threshold', 80);

        $this->assertDatabaseHas('qa_scores', [
            'call_id' => $recording->call_id,
            'status' => 'submitted',
            'version' => 1,
        ]);
    }

    private function createRecording(?User $agent = null): Recording
    {
        $provider = Provider::factory()->create();

        $call = Call::query()->create([
            'provider_call_id' => 'call-'.uniqid('', true),
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'from_number' => '+15550001234',
            'to_number' => '+15550004321',
            'agent_id' => $agent?->id,
            'started_at' => Carbon::parse('2024-05-10 10:00:00'),
            'ended_at' => Carbon::parse('2024-05-10 10:08:00'),
            'duration_sec' => 480,
            'disposition' => 'completed',
            'queue' => 'support',
            'metadata' => ['source' => 'test'],
        ]);

        return Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'local_path' => 'recordings/test.mp3',
            'storage_backend' => 'local',
            'format' => 'mp3',
            'status' => 'ready',
            'waveform_json' => [0.1, 0.4, 0.6],
        ]);
    }
}
