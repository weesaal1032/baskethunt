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
