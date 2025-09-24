<?php

namespace App\Services\Settings;

use App\Repositories\Contracts\SettingsRepositoryInterface;
use Illuminate\Support\Str;

class SettingsService
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * @var array<string, string>
     */
    private array $configFallbacks = [
        'app.name' => 'app.name',
        'app.timezone' => 'app.timezone',
        'app.url' => 'app.url',
        'telephony.provider.base_url' => 'callhub.telephony.base_url',
        'telephony.provider.auth_type' => 'callhub.telephony.auth_type',
        'telephony.provider.api_key' => 'callhub.telephony.api_key',
        'telephony.provider.pagination_size' => 'callhub.telephony.pagination_size',
        'telephony.provider.rate_limit' => 'callhub.telephony.rate_limit',
        'telephony.provider.poll_window_days' => 'callhub.telephony.poll_window_days',
        'telephony.provider.request_headers' => 'callhub.telephony.headers',
        'telephony.provider.query_defaults' => 'callhub.telephony.query',
        'telephony.provider.mapping' => 'callhub.telephony.mapping',
        'telephony.provider.calls_endpoint' => 'callhub.telephony.calls_endpoint',
        'telephony.provider.recording_endpoint' => 'callhub.telephony.recording_endpoint',
        'storage.default' => 'callhub.storage.default',
        'storage.s3.key' => 'callhub.storage.s3.access_key',
        'storage.s3.secret' => 'callhub.storage.s3.secret_key',
        'storage.s3.region' => 'callhub.storage.s3.region',
        'storage.s3.bucket' => 'callhub.storage.s3.bucket',
        'storage.s3.prefix' => 'callhub.storage.s3.prefix',
        'transcription.engine' => 'callhub.transcription.engine',
        'transcription.api_key' => 'callhub.transcription.api_key',
        'transcription.cli_path' => 'callhub.transcription.cli_path',
        'transcription.language' => 'callhub.transcription.language',
        'transcription.max_concurrent' => 'callhub.transcription.max_concurrent',
        'transcription.api_timeout' => 'callhub.transcription.api_timeout',
        'transcription.cli_model' => 'callhub.transcription.cli_model',
        'transcription.cli_threads' => 'callhub.transcription.cli_threads',
        'transcription.cli_timeout' => 'callhub.transcription.cli_timeout',
        'transcription.daily_limit_minutes' => 'callhub.transcription.daily_limit_minutes',
        'notifications.mail.host' => 'callhub.notifications.mail.host',
        'notifications.mail.port' => 'callhub.notifications.mail.port',
        'notifications.mail.username' => 'callhub.notifications.mail.username',
        'notifications.mail.password' => 'callhub.notifications.mail.password',
        'notifications.mail.encryption' => 'callhub.notifications.mail.encryption',
        'notifications.mail.from_address' => 'callhub.notifications.mail.from_address',
        'notifications.mail.from_name' => 'callhub.notifications.mail.from_name',
        'notifications.slack.webhook' => 'callhub.notifications.slack.webhook',
        'privacy.pii_masking' => 'callhub.privacy.pii_masking',
        'privacy.retention_months' => 'callhub.privacy.retention_months',
    ];

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $record = $this->settings->findByKey($key);

        if ($record !== null) {
            $value = $this->decodeValue($record->value);
        } else {
            $value = $this->fallbackValue($key, $default);
        }

        return $this->cache[$key] = $value;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function set(string $key, mixed $value): void
    {
        $encoded = $this->encodeValue($value);
        $this->settings->upsert($key, $encoded);
        $this->cache[$key] = $value;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function getMany(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    private function fallbackValue(string $key, mixed $default = null): mixed
    {
        $configKey = $this->configFallbacks[$key] ?? null;

        if ($configKey !== null) {
            $configValue = config($configKey);
            if ($configValue !== null) {
                return $configValue;
            }
        }

        $envKey = Str::upper(str_replace('.', '_', $key));

        return env($envKey, $default);
    }

    private function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function decodeValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($this->isJson($value)) {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        }

        return $value;
    }

    private function isJson(string $value): bool
    {
        if ($value === '' || ! str_starts_with($value, '{') && ! str_starts_with($value, '[')) {
            return false;
        }

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return true;
    }
}
