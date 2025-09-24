<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TelephonyProviderController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Installer\InstallerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::prefix('install')->name('install.')->middleware('installer.unlocked')->group(function () {
    Route::get('/', [InstallerController::class, 'index'])->name('index');
    Route::post('/', [InstallerController::class, 'store'])->name('store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::middleware('role:admin,lead')->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::get('logs', [DashboardController::class, 'logs'])->name('logs');
        Route::get('settings/general', [SettingsController::class, 'edit'])->name('settings.general');
        Route::post('settings/general', [SettingsController::class, 'update'])->name('settings.general.update');

        Route::prefix('providers')->name('providers.')->group(function (): void {
            Route::get('telephony/mapping', [TelephonyProviderController::class, 'mapping'])->name('telephony.mapping');
            Route::post('telephony/mapping', [TelephonyProviderController::class, 'updateMapping'])->name('telephony.mapping.update');
            Route::post('telephony/preview', [TelephonyProviderController::class, 'preview'])->name('telephony.preview');
        });
    });
});

Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'authenticate']);

        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.update');

        Route::get('otp', [OtpController::class, 'show'])->name('otp.show');
        Route::post('otp', [OtpController::class, 'verify'])->name('otp.verify');
        Route::post('otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
    });

    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
});
