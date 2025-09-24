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
