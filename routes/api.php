<?php

use App\Http\Controllers\Api\Internal\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->name('api.internal.')->group(function () {
    Route::get('status', [SystemStatusController::class, 'show'])->name('status');
});
