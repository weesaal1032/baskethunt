<?php

namespace Tests\Unit\Policies;

use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Transcript;
use App\Models\User;
use App\Policies\QaScorePolicy;
use App\Policies\RecordingPolicy;
use App\Policies\TranscriptPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_and_transcript_view_permissions(): void
    {
        $policy = new RecordingPolicy();
        $transcriptPolicy = new TranscriptPolicy();

        foreach (['admin', 'lead', 'qa', 'readonly'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue($policy->viewAny($user));
            $this->assertTrue($transcriptPolicy->viewAny($user));
        }

        $user = User::factory()->create(['role' => 'readonly', 'status' => 'active']);
        $this->assertTrue($policy->view($user, new Recording()));
        $this->assertTrue($transcriptPolicy->view($user, new Transcript()));
    }

    public function test_qa_score_permissions(): void
    {
        $policy = new QaScorePolicy();
        $qa = User::factory()->create(['role' => 'qa']);
        $lead = User::factory()->create(['role' => 'lead']);
        $readonly = User::factory()->create(['role' => 'readonly']);

        $score = new QaScore(['scored_by' => $qa->id]);

        $this->assertTrue($policy->viewAny($qa));
        $this->assertTrue($policy->view($qa, $score));
        $this->assertTrue($policy->create($qa));
        $this->assertTrue($policy->update($qa, $score));

        $this->assertTrue($policy->viewAny($lead));
        $this->assertTrue($policy->update($lead, $score));

        $this->assertFalse($policy->viewAny($readonly));
    }
}
