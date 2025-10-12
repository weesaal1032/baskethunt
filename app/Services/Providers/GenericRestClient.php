<?php

namespace App\Services\Providers;

use App\Services\Providers\Exceptions\TelephonyClientException;
use App\Services\Providers\ValueObjects\TelephonyCallPage;
use App\Services\Settings\SettingsService;
use App\Support\Providers\TelephonyMappingDefaults;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

class GenericRestClient implements TelephonyClientInterface
{
    private const DEFAULT_CALLS_ENDPOINT = '/calls';
    private const DEFAULT_RECORDING_ENDPOINT = '/calls/{callId}/recording';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly SettingsService $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function listCalls(CarbonInterface $from, CarbonInterface $to, ?string $cursor = null): TelephonyCallPage
    {
        $endpoint = $this->normalizeEndpoint(
            (string) $this->settings->get('telephony.provider.calls_endpoint', self::DEFAULT_CALLS_ENDPOINT)
        );

        $query = $this->defaultQueryParameters($from, $to, $cursor);
        $response = $this->performRequest('GET', $endpoint, $query);

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new TelephonyClientException('Telephony API responded with an unexpected payload.');
        }

        $calls = $this->extractCalls($payload);
        $mappedCalls = $this->mapCalls($calls);
        $nextCursor = $this->resolveCursor($payload);

        return new TelephonyCallPage($mappedCalls, $nextCursor, $payload);
    }

    public function getRecordingUrl(string $providerCallId): string
    {
        $endpointTemplate = (string) $this->settings->get(
            'telephony.provider.recording_endpoint',
            self::DEFAULT_RECORDING_ENDPOINT
        );

        $endpoint = str_replace('{callId}', urlencode($providerCallId), $endpointTemplate);
        $endpoint = $this->normalizeEndpoint($endpoint);

        $response = $this->performRequest('GET', $endpoint);

        if ($response->status() === 204) {
            return '';
        }

        $payload = $response->json();

        if (is_array($payload) && isset($payload['url']) && is_string($payload['url'])) {
            return $payload['url'];
        }

        if ($response->successful()) {
            return trim((string) $response->body());
        }

        throw new TelephonyClientException('Unable to resolve remote recording URL.');
    }

    /**
     * @param array<int, array<string, mixed>> $calls
     */
    private function mapCalls(array $calls): Collection
    {
        $mapping = $this->normalizeMapping($this->settings->get('telephony.provider.mapping', []));

        return collect($calls)->map(static function (array $call) use ($mapping): array {
            $transformed = [];
            foreach ($mapping as $key => $path) {
                if (! is_string($path) || $path === '') {
                    $transformed[$key] = null;

                    continue;
                }

                $transformed[$key] = data_get($call, $path);
            }

            $transformed['raw'] = $call;

            return $transformed;
        });
    }

    /**
     * @param mixed $mapping
     * @return array<string, string|null>
     */
    private function normalizeMapping(mixed $mapping): array
    {
        $defaults = TelephonyMappingDefaults::values();

        if (! is_array($mapping)) {
            $mapping = [];
        }

        $normalized = [];

        foreach ($defaults as $key => $path) {
            $normalized[$key] = $path;
        }

        foreach ($mapping as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_string($value) && $value !== '') {
                $normalized[$key] = $value;

                continue;
            }

            if ($value === null || $value === '') {
                $normalized[$key] = null;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractCalls(array $payload): array
    {
        $data = $payload['data'] ?? $payload['items'] ?? $payload;

        if (! is_array($data)) {
            throw new TelephonyClientException('Telephony API did not return a collection of calls.');
        }

        if (array_is_list($data)) {
            return $data;
        }

        return array_values($data);
    }

    private function resolveCursor(array $payload): ?string
    {
        $cursor = $payload['next_cursor']
            ?? data_get($payload, 'meta.next_cursor')
            ?? data_get($payload, 'links.next')
            ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /**
     * @param array<string, mixed>|null $query
     */
    private function performRequest(string $method, string $endpoint, ?array $query = null): Response
    {
        $request = $this->prepareRequest();

        $attempts = 0;
        $maxAttempts = $this->maxAttempts();
        $backoff = $this->initialBackoffMs();
        $multiplier = $this->backoffMultiplier();

        do {
            $attempts++;
            $startedAt = microtime(true);

            $response = $request->send($method, $endpoint, ['query' => $query ?? []]);

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            $this->logger->info('Telephony request completed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'duration_ms' => $durationMs,
                'attempt' => $attempts,
            ]);

            if ($response->successful()) {
                return $response;
            }

            if (! $this->shouldRetry($response)) {
                break;
            }

            usleep($backoff * 1000);
            $backoff = (int) ($backoff * $multiplier);
        } while ($attempts < $maxAttempts);

        $message = sprintf('Telephony request failed after %d attempts.', $attempts);
        $this->logger->error($message, [
            'endpoint' => $endpoint,
            'status' => isset($response) ? $response->status() : null,
            'body' => isset($response) ? $this->safeBody($response->body()) : null,
        ]);

        throw new TelephonyClientException($message);
    }

    private function prepareRequest(): PendingRequest
    {
        $baseUrl = (string) $this->settings->get('telephony.provider.base_url');
        if ($baseUrl === '') {
            throw new TelephonyClientException('Telephony provider base URL is not configured.');
        }

        $request = $this->http->baseUrl(rtrim($baseUrl, '/'))
            ->acceptJson()
            ->timeout(15)
            ->throw(false);

        $headers = $this->resolveHeaders();

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        return $request;
    }

    /**
     * @return array<string, string>
     */
    private function resolveHeaders(): array
    {
        $headers = $this->settings->get('telephony.provider.request_headers', []);
        if (! is_array($headers)) {
            $headers = [];
        }

        $authType = (string) $this->settings->get('telephony.provider.auth_type', 'header');
        $apiKey = (string) $this->settings->get('telephony.provider.api_key', '');

        if ($apiKey !== '') {
            if ($authType === 'bearer') {
                $headers['Authorization'] = 'Bearer ' . $apiKey;
            } else {
                $headers['X-API-Key'] = $apiKey;
            }
        }

        return array_filter($headers, static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultQueryParameters(CarbonInterface $from, CarbonInterface $to, ?string $cursor): array
    {
        $query = $this->settings->get('telephony.provider.query_defaults', []);
        if (! is_array($query)) {
            $query = [];
        }

        $query['from'] = $from->toIso8601String();
        $query['to'] = $to->toIso8601String();

        $query['per_page'] = (int) $this->settings->get('telephony.provider.pagination_size', 100);

        if ($cursor !== null) {
            $query['cursor'] = $cursor;
            $query['page'] = $cursor;
        }

        return $query;
    }

    private function maxAttempts(): int
    {
        $rateLimit = $this->rateLimitConfiguration();

        return (int) ($rateLimit['max_attempts'] ?? 3);
    }

    private function initialBackoffMs(): int
    {
        $rateLimit = $this->rateLimitConfiguration();

        return (int) (($rateLimit['backoff_ms'] ?? 500) ?: 500);
    }

    private function backoffMultiplier(): float
    {
        $rateLimit = $this->rateLimitConfiguration();

        $multiplier = (float) ($rateLimit['backoff_multiplier'] ?? 2.0);

        return $multiplier > 1 ? $multiplier : 2.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function rateLimitConfiguration(): array
    {
        $config = $this->settings->get('telephony.provider.rate_limit', []);
        if (! is_array($config)) {
            $decoded = null;
            if (is_string($config) && Str::of($config)->isNotEmpty()) {
                try {
                    /** @var array<string, mixed> $decoded */
                    $decoded = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    $decoded = [];
                }
            }

            return $decoded ?? [];
        }

        return $config;
    }

    private function shouldRetry($response): bool
    {
        $status = $response->status();

        if ($status === 429) {
            return true;
        }

        return $status >= 500 && $status < 600;
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        if ($endpoint === '') {
            return '/';
        }

        return '/' . ltrim($endpoint, '/');
    }

    private function safeBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $truncated = Str::limit($body, 500);

        return $truncated;
    }
}
