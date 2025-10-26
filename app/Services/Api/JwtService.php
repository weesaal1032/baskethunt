<?php

namespace App\Services\Api;

use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use RuntimeException;

final class JwtService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token structure.');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        $header = $this->decodeSegment($headerEncoded);
        $payload = $this->decodeSegment($payloadEncoded);

        if (! is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('Unsupported token algorithm.');
        }

        $secret = $this->secret();

        if ($secret === null || $secret === '') {
            throw new RuntimeException('Internal API secret is not configured.');
        }

        $expectedSignature = $this->sign("{$headerEncoded}.{$payloadEncoded}", $secret);

        if (! hash_equals($expectedSignature, $signatureEncoded)) {
            throw new RuntimeException('Token signature mismatch.');
        }

        $now = CarbonImmutable::now();

        if (isset($payload['nbf']) && $now->lt(CarbonImmutable::createFromTimestampUTC((int) $payload['nbf']))) {
            throw new RuntimeException('Token not yet valid.');
        }

        if (isset($payload['exp']) && $now->greaterThanOrEqualTo(CarbonImmutable::createFromTimestampUTC((int) $payload['exp']))) {
            throw new RuntimeException('Token expired.');
        }

        return $payload;
    }

    private function secret(): ?string
    {
        $secret = $this->settings->get('api.internal.secret');

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        $fallback = config('callhub.api.internal.secret');

        return is_string($fallback) ? $fallback : null;
    }

    /**
     * @return array<string, mixed>|string
     */
    private function decodeSegment(string $segment): array|string
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Unable to decode token segment.');
        }

        $json = json_decode($decoded, true);

        return is_array($json) ? $json : $decoded;
    }

    private function sign(string $data, string $secret): string
    {
        $signature = hash_hmac('sha256', $data, $secret, true);

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
