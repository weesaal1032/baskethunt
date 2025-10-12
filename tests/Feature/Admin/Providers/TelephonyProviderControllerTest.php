<?php

namespace Tests\Feature\Admin\Providers;

use App\Models\User;
use App\Services\Providers\TelephonyClientInterface;
use App\Services\Providers\ValueObjects\TelephonyCallPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelephonyProviderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_mapping_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.providers.telephony.mapping'));

        $response->assertOk();
        $response->assertSeeText('Telephony API Mapping');
    }

    public function test_admin_can_store_mapping_configuration(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'calls_endpoint' => '/api/calls',
            'recording_endpoint' => '/api/calls/{callId}/recording',
            'telephony_headers_json' => json_encode(['Accept' => 'application/json']),
            'telephony_query_json' => json_encode(['include' => 'recording']),
            'mapping' => [
                ['key' => 'provider_call_id', 'path' => 'attributes.id'],
                ['key' => 'from_number', 'path' => 'attributes.from'],
                ['key' => 'queue', 'path' => 'attributes.queueId'],
                ['key' => 'recording_url', 'path' => 'relationships.recording.data.url'],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('admin.providers.telephony.mapping.update'), $payload);

        $response->assertRedirect(route('admin.providers.telephony.mapping'));
        $response->assertSessionHas('status', 'Telephony provider mapping saved.');

        $this->assertDatabaseHas('settings', [
            'key' => 'telephony.provider.calls_endpoint',
            'value' => '/api/calls',
        ]);

        $mapping = json_decode(
            (string) DB::table('settings')->where('key', 'telephony.provider.mapping')->value('value'),
            true
        );

        $this->assertSame('attributes.id', $mapping['provider_call_id']);
        $this->assertSame('attributes.from', $mapping['from_number']);
        $this->assertSame('attributes.queueId', $mapping['queue']);

        $headers = json_decode(
            (string) DB::table('settings')->where('key', 'telephony.provider.request_headers')->value('value'),
            true
        );
        $this->assertSame(['Accept' => 'application/json'], $headers);
    }

    public function test_preview_route_flashes_call_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $mockClient = $this->createMock(TelephonyClientInterface::class);
        $mockClient->method('listCalls')->willReturn(
            new TelephonyCallPage(collect([
                [
                    'provider_call_id' => 'abc123',
                    'from_number' => '+15550000001',
                    'to_number' => '+15550000099',
                    'status' => 'completed',
                    'started_at' => '2024-05-10T12:00:00Z',
                ],
            ]), 'cursor-2')
        );

        $this->app->instance(TelephonyClientInterface::class, $mockClient);

        $response = $this->actingAs($admin)->post(route('admin.providers.telephony.preview'), [
            'from' => '2024-05-01',
            'to' => '2024-05-02',
        ]);

        $response->assertRedirect(route('admin.providers.telephony.mapping'));
        $response->assertSessionHas('telephony_preview');
        $this->assertArrayHasKey('calls', session('telephony_preview'));
    }
}
