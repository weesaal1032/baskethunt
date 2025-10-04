<?php

namespace Tests\Feature\Api\Internal;

use App\Models\Call;
use App\Models\Provider;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Transcript;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class CallsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_token_returns_unauthorised(): void
    {
        $response = $this->getJson('/api/internal/calls');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'unauthorised']);
    }

    public function test_calls_endpoint_returns_paginated_payload(): void
    {
        config(['callhub.api.internal.secret' => 'test-secret']);

        $agent = User::factory()->create(['role' => 'qa', 'name' => 'Agent API']);
        $provider = Provider::factory()->create();

        $call = Call::query()->create([
            'provider_call_id' => 'api-call-1',
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'from_number' => '+15550001111',
            'to_number' => '+15550002222',
            'agent_id' => $agent->id,
            'started_at' => Carbon::parse('2024-05-10 10:00:00'),
            'ended_at' => Carbon::parse('2024-05-10 10:10:00'),
            'duration_sec' => 600,
            'disposition' => 'completed',
            'queue' => 'sales',
        ]);

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.test/audio.mp3',
            'storage_backend' => 'local',
            'status' => 'ready',
            'format' => 'mp3',
            'bytes' => 2048,
            'checksum' => 'checksum',
        ]);

        Transcript::query()->create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'Transcript text',
            'status' => 'ready',
        ]);

        QaScore::query()->create([
            'call_id' => $call->id,
            'scored_by' => $agent->id,
            'version' => 1,
            'status' => 'submitted',
            'total_score' => 88,
            'possible_score' => 100,
            'passed' => true,
        ]);

        $token = $this->jwt();

        $response = $this->getJson('/api/internal/calls?per_page=25', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 25);
        $response->assertJsonPath('data.0.provider_call_id', 'api-call-1');
        $response->assertJsonPath('data.0.agent.name', 'Agent API');
        $response->assertJsonPath('data.0.recording.status', 'ready');
        $response->assertJsonPath('data.0.qa.passed', true);
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
