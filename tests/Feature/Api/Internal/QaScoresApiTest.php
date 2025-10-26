<?php

namespace Tests\Feature\Api\Internal;

use App\Models\Call;
use App\Models\Provider;
use App\Models\QaScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class QaScoresApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_returns_scores(): void
    {
        config(['callhub.api.internal.secret' => 'test-secret']);

        $agent = User::factory()->create(['role' => 'qa', 'team' => 'Team A']);
        $scorer = User::factory()->create(['role' => 'admin']);
        $provider = Provider::factory()->create();

        $call = Call::query()->create([
            'provider_call_id' => 'qa-call-1',
            'provider_id' => $provider->id,
            'direction' => 'outbound',
            'from_number' => '+15550112233',
            'to_number' => '+15550119900',
            'agent_id' => $agent->id,
            'started_at' => Carbon::parse('2024-05-09 08:00:00'),
            'ended_at' => Carbon::parse('2024-05-09 08:07:00'),
            'duration_sec' => 420,
            'disposition' => 'completed',
            'queue' => 'support',
        ]);

        QaScore::query()->create([
            'call_id' => $call->id,
            'scored_by' => $scorer->id,
            'version' => 3,
            'status' => 'submitted',
            'total_score' => 84,
            'possible_score' => 100,
            'passed' => true,
            'tags' => ['coaching'],
            'comments' => 'Solid call.',
            'submitted_at' => Carbon::parse('2024-05-09 09:00:00'),
        ]);

        $token = $this->jwt();

        $response = $this->getJson('/api/internal/qa-scores?team=Team%20A', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.0.call_id', $call->id);
        $response->assertJsonPath('data.0.agent.team', 'Team A');
        $response->assertJsonPath('data.0.tags.0', 'coaching');
        $response->assertJsonPath('meta.filters.team', 'Team A');
    }

    private function jwt(array $overrides = []): string
    {
        $secret = config('callhub.api.internal.secret', 'test-secret');

        $header = $this->base64Url(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64Url(json_encode(array_merge([
            'iss' => 'callhub-tests',
            'aud' => 'internal-api',
            'exp' => Carbon::now()->addMinutes(5)->timestamp,
        ], $overrides), JSON_THROW_ON_ERROR));

        $signature = $this->base64Url(hash_hmac('sha256', $header . '.' . $payload, $secret, true));

        return $header . '.' . $payload . '.' . $signature;
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
