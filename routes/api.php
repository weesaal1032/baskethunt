<?php

use App\Http\Controllers\Api\Internal\CallsController;
use App\Http\Controllers\Api\Internal\QaScoresController;
use App\Http\Controllers\Api\Internal\SystemStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->name('api.internal.')->middleware('internal.api')->group(function () {
    Route::get('status', [SystemStatusController::class, 'show'])->name('status');
    Route::get('calls', [CallsController::class, 'index'])->name('calls.index');
    Route::get('qa-scores', [QaScoresController::class, 'index'])->name('qa-scores.index');
});
