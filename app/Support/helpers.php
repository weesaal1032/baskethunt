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

        $length = strlen($digits);
        $visiblePrefix = substr($digits, 0, min(2, max($length - 3, 0)));
        $visibleSuffix = substr($digits, -3);
        $maskedLength = max($length - strlen($visiblePrefix) - strlen($visibleSuffix), 0);
        $mask = str_repeat('•', $maskedLength);
        $prefix = str_starts_with($trimmed, '+') ? '+' : '';

        if ($visiblePrefix === '' && $maskedLength === 0) {
            return $prefix.$visibleSuffix;
        }

        return $prefix.$visiblePrefix.$mask.$visibleSuffix;
    }
}
