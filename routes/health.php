<?php

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    /** @var SettingsService $settings */
    $settings = app(SettingsService::class);

    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'telephony_last_poll_at' => $settings->get('telephony.provider.last_polled_at'),
    ]);
});
