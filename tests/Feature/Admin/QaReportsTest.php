<?php

namespace Tests\Feature\Admin;

use App\Models\Call;
use App\Models\Provider;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QaReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_qa_reports_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'lead', 'team' => 'Support']);
        $recording = $this->seedSubmittedScore($agent, 87);

        $response = $this->actingAs($admin)->get(route('admin.qa.reports'));

        $response->assertOk();
        $response->assertSeeText('QA Reporting');
        $response->assertSeeText($agent->name);
        $response->assertSee('87.00%');
        $response->assertSee((string) $recording->call_id);
    }

    public function test_admin_can_export_qa_scores_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agent = User::factory()->create(['role' => 'lead', 'team' => 'Support']);
        $this->seedSubmittedScore($agent, 92);

        $response = $this->actingAs($admin)->get(route('admin.qa.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Version,Status,Score (%)', $csv);
        $this->assertStringContainsString('92', $csv);
        $this->assertStringContainsString('Support', $csv);
    }

    private function seedSubmittedScore(User $agent, int $score): Recording
    {
        $provider = Provider::factory()->create();

        $call = Call::query()->create([
            'provider_call_id' => 'report-'.uniqid('', true),
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'from_number' => '+15550005555',
            'to_number' => '+15550009999',
            'agent_id' => $agent->id,
            'started_at' => Carbon::parse('2024-05-09 09:00:00'),
            'ended_at' => Carbon::parse('2024-05-09 09:12:00'),
            'duration_sec' => 720,
            'disposition' => 'completed',
            'queue' => 'support',
            'metadata' => ['segment' => 'reports'],
        ]);

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'local_path' => 'recordings/report.mp3',
            'storage_backend' => 'local',
            'format' => 'mp3',
            'status' => 'ready',
        ]);

        QaScore::query()->create([
            'call_id' => $call->id,
            'scored_by' => $agent->id,
            'version' => 1,
            'status' => 'submitted',
            'rubric_version' => 1,
            'total_score' => $score,
            'possible_score' => 100,
            'passed' => $score >= 80,
            'responses' => ['q_greeting_open' => true],
            'score_breakdown' => ['q_greeting_open' => ['earned' => 10, 'possible' => 10]],
            'comments' => 'Report seed',
            'tags' => ['report'],
            'submitted_at' => Carbon::parse('2024-05-10 10:00:00'),
        ]);

        return $recording;
    }
}
