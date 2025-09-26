<?php

namespace Tests\Unit\Services\Providers;

use App\Services\Providers\Exceptions\TelephonyClientException;
use App\Services\Providers\GenericRestClient;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tests\TestCase;

class GenericRestClientTest extends TestCase
{
    private SettingsService&MockObject $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = $this->createMock(SettingsService::class);
    }

    public function test_list_calls_maps_payload_and_returns_cursor(): void
    {
        Http::fake([
            'https://telephony.test/calls*' => Http::response([
                'data' => [
                    [
                        'id' => 'abc123',
                        'from' => '+15550000001',
                        'to' => '+15550000099',
                        'started_at' => '2024-05-10T12:00:00Z',
                        'duration' => 360,
                        'status' => 'completed',
                        'recording_url' => 'https://telephony.test/recordings/abc123.mp3',
                    ],
                ],
                'next_cursor' => 'cursor-1',
            ], 200),
        ]);

        $this->settings->method('get')->willReturnCallback(function (string $key, $default = null) {
            $map = [
                'telephony.provider.base_url' => 'https://telephony.test',
                'telephony.provider.calls_endpoint' => '/calls',
                'telephony.provider.request_headers' => [],
                'telephony.provider.auth_type' => 'header',
                'telephony.provider.api_key' => 'api-key',
                'telephony.provider.query_defaults' => ['include' => 'recording'],
                'telephony.provider.pagination_size' => 50,
                'telephony.provider.rate_limit' => ['max_attempts' => 1],
                'telephony.provider.mapping' => [
                    'provider_call_id' => 'id',
                    'from_number' => 'from',
                    'to_number' => 'to',
                    'started_at' => 'started_at',
                    'duration' => 'duration',
                    'status' => 'status',
                    'recording_url' => 'recording_url',
                ],
            ];

            return $map[$key] ?? $default;
        });

        $client = new GenericRestClient(app(HttpFactory::class), $this->settings, new NullLogger());

        $from = CarbonImmutable::parse('2024-05-01T00:00:00Z');
        $to = CarbonImmutable::parse('2024-05-02T00:00:00Z');

        $page = $client->listCalls($from, $to);

        $call = $page->calls()->first();
        $this->assertSame('abc123', $call['provider_call_id']);
        $this->assertSame('+15550000001', $call['from_number']);
        $this->assertSame('cursor-1', $page->nextCursor());

        Http::assertSent(function ($request) use ($from, $to) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);

            return str_starts_with($request->url(), 'https://telephony.test/calls')
                && ($params['include'] ?? null) === 'recording'
                && ($params['from'] ?? null) === $from->toIso8601String()
                && ($params['to'] ?? null) === $to->toIso8601String()
                && (int) ($params['per_page'] ?? 0) === 50;
        });
    }

    public function test_get_recording_url_returns_payload_value(): void
    {
        Http::fake([
            'https://telephony.test/calls/abc123/recording' => Http::response([
                'url' => 'https://telephony.test/recordings/abc123.mp3',
            ], 200),
        ]);

        $this->settings->method('get')->willReturnCallback(function (string $key, $default = null) {
            $map = [
                'telephony.provider.base_url' => 'https://telephony.test',
                'telephony.provider.recording_endpoint' => '/calls/{callId}/recording',
                'telephony.provider.request_headers' => [],
                'telephony.provider.auth_type' => 'bearer',
                'telephony.provider.api_key' => 'secret',
                'telephony.provider.rate_limit' => ['max_attempts' => 1],
            ];

            return $map[$key] ?? $default;
        });

        $client = new GenericRestClient(app(HttpFactory::class), $this->settings, new NullLogger());

        $url = $client->getRecordingUrl('abc123');

        $this->assertSame('https://telephony.test/recordings/abc123.mp3', $url);

        Http::assertSentCount(1);
    }

    public function test_request_failure_throws_exception(): void
    {
        Http::fake([
            'https://telephony.test/calls*' => Http::response(null, 500),
        ]);

        $this->settings->method('get')->willReturnCallback(function (string $key, $default = null) {
            $map = [
                'telephony.provider.base_url' => 'https://telephony.test',
                'telephony.provider.calls_endpoint' => '/calls',
                'telephony.provider.request_headers' => [],
                'telephony.provider.auth_type' => 'header',
                'telephony.provider.api_key' => 'api-key',
                'telephony.provider.query_defaults' => [],
                'telephony.provider.pagination_size' => 25,
                'telephony.provider.rate_limit' => ['max_attempts' => 1],
                'telephony.provider.mapping' => [],
            ];

            return $map[$key] ?? $default;
        });

        $client = new GenericRestClient(app(HttpFactory::class), $this->settings, new NullLogger());

        $this->expectException(TelephonyClientException::class);

        $client->listCalls(CarbonImmutable::now()->subDay(), CarbonImmutable::now());
    }
}
