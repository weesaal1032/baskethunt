<?php

namespace Tests\Feature\Admin;

use App\Models\Call;
use App\Models\Provider;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Settings;
use App\Models\Transcript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class CallExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_calls_csv(): void
    {
        Settings::query()->create([
            'key' => 'privacy.pii_masking',
            'value' => '1',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'qa', 'name' => 'Jane Agent']);
        $provider = Provider::factory()->create(['name' => 'Telephony Inc']);

        $call = Call::query()->create([
            'provider_call_id' => 'call-export-1',
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

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'storage_backend' => 'local',
            'status' => 'ready',
            'format' => 'mp3',
            'bytes' => 1024,
            'checksum' => 'checksum',
        ]);

        Transcript::query()->create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'Transcript content',
            'status' => 'ready',
        ]);

        QaScore::query()->create([
            'call_id' => $call->id,
            'scored_by' => $admin->id,
            'version' => 1,
            'status' => 'submitted',
            'total_score' => 92,
            'possible_score' => 100,
            'passed' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.calls.export', [
            'direction' => 'outbound',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Call ID', $csv);
        $this->assertStringContainsString('call-export-1', $csv);
        $this->assertStringContainsString('••••4321', $csv);
        $this->assertStringNotContainsString('+15557654321', $csv);
    }
}
