<?php

namespace Tests\Feature\Console;

use App\Models\Audit;
use App\Models\Call;
use App\Models\Provider;
use App\Models\Recording;
use App\Models\Transcript;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteCallDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_call_command_removes_associated_assets(): void
    {
        Storage::fake('local');

        User::factory()->create(['role' => 'admin']);
        $qaUser = User::factory()->create(['role' => 'qa']);
        $provider = Provider::factory()->create();

        $call = Call::query()->create([
            'provider_call_id' => 'provider-456',
            'provider_id' => $provider->id,
            'direction' => 'outbound',
            'from_number' => '+15550000002',
            'to_number' => '+15550000003',
            'agent_id' => null,
            'started_at' => CarbonImmutable::now()->subDay(),
            'ended_at' => CarbonImmutable::now()->subDay()->addMinutes(6),
            'duration_sec' => 360,
            'disposition' => 'completed',
            'queue' => 'sales',
            'metadata' => [],
        ]);

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.com/outbound.mp3',
            'local_path' => 'recordings/provider-456.mp3',
            'storage_backend' => 'local',
            'format' => 'mp3',
            'bytes' => 2048,
            'checksum' => 'def456',
            'waveform_json' => null,
            'status' => 'ready',
        ]);

        $transcript = Transcript::query()->create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'goodbye world',
            'confidence' => 0.8,
            'segments' => [],
            'status' => 'ready',
        ]);

        Storage::disk('local')->put($recording->local_path, 'audio');

        $qaScore = $call->qaScores()->create([
            'scored_by' => $qaUser->id,
            'rubric' => ['score' => 90],
            'total_score' => 90,
            'comments' => 'Great job',
        ]);

        Log::spy();
        Artisan::call('privacy:delete-call', ['callId' => $call->id, '--dry-run' => true]);

        $this->assertDatabaseHas('calls', ['id' => $call->id]);
        Log::shouldHaveReceived('info')->withArgs(function (string $message) {
            return str_contains($message, 'Delete-call dry-run');
        })->atLeast()->once();

        Log::spy();
        Artisan::call('privacy:delete-call', ['callId' => $call->id]);

        $this->assertDatabaseMissing('calls', ['id' => $call->id]);
        $this->assertDatabaseMissing('recordings', ['id' => $recording->id]);
        $this->assertDatabaseMissing('transcripts', ['id' => $transcript->id]);
        $this->assertFalse(Storage::disk('local')->exists('recordings/provider-456.mp3'));
        $this->assertDatabaseMissing('qa_scores', ['id' => $qaScore->id]);
        $this->assertGreaterThan(0, Audit::query()->where('action', 'call.delete_request')->count());
    }
}
