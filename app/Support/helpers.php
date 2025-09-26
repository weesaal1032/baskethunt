<?php

use App\Services\Settings\SettingsService;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        /** @var SettingsService $service */
        $service = app(SettingsService::class);

        return $service->get($key, $default);
    }
}

if (! function_exists('format_phone')) {
    function format_phone(?string $number, bool $mask = false): string
    {
        if ($number === null) {
            return 'Unknown';
        }

        $trimmed = trim($number);

        if ($trimmed === '') {
            return 'Unknown';
        }

        if (! $mask) {
            return $trimmed;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '••••';
        }

        $lastFour = substr($digits, -4);
        $prefix = str_starts_with($trimmed, '+') ? '+' : '';

        return sprintf('%s••••%s', $prefix, $lastFour);
    }
}
