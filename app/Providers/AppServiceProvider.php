<?php

namespace App\Providers;

use App\Repositories\Contracts\AdminRepositoryInterface;
use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\CallsRepositoryInterface;
use App\Repositories\Contracts\HealthRepositoryInterface;
use App\Repositories\Contracts\InstallerRepositoryInterface;
use App\Repositories\Contracts\JobsRepositoryInterface;
use App\Repositories\Contracts\ProvidersRepositoryInterface;
use App\Repositories\Contracts\QARepositoryInterface;
use App\Repositories\Contracts\RecordingsRepositoryInterface;
use App\Repositories\Contracts\SettingsRepositoryInterface;
use App\Repositories\Contracts\StorageRepositoryInterface;
use App\Repositories\Contracts\TranscriptsRepositoryInterface;
use App\Repositories\Eloquent\AdminRepository;
use App\Repositories\Eloquent\AuditRepository;
use App\Repositories\Eloquent\AuthRepository;
use App\Repositories\Eloquent\CallsRepository;
use App\Repositories\Eloquent\HealthRepository;
use App\Repositories\Eloquent\InstallerRepository;
use App\Repositories\Eloquent\JobsRepository;
use App\Repositories\Eloquent\ProvidersRepository;
use App\Repositories\Eloquent\QARepository;
use App\Repositories\Eloquent\RecordingsRepository;
use App\Repositories\Eloquent\SettingsRepository;
use App\Repositories\Eloquent\StorageRepository;
use App\Repositories\Eloquent\TranscriptsRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);
        $this->app->bind(ProvidersRepositoryInterface::class, ProvidersRepository::class);
        $this->app->bind(CallsRepositoryInterface::class, CallsRepository::class);
        $this->app->bind(RecordingsRepositoryInterface::class, RecordingsRepository::class);
        $this->app->bind(TranscriptsRepositoryInterface::class, TranscriptsRepository::class);
        $this->app->bind(QARepositoryInterface::class, QARepository::class);
        $this->app->bind(JobsRepositoryInterface::class, JobsRepository::class);
        $this->app->bind(InstallerRepositoryInterface::class, InstallerRepository::class);
        $this->app->bind(AdminRepositoryInterface::class, AdminRepository::class);
        $this->app->bind(AuditRepositoryInterface::class, AuditRepository::class);
        $this->app->bind(StorageRepositoryInterface::class, StorageRepository::class);
        $this->app->bind(HealthRepositoryInterface::class, HealthRepository::class);
    }

    public function boot(): void
    {
        View::composer('layouts.base', function ($view): void {
            $view->with('installerLocked', File::exists(storage_path('installed.flag')));
        });
    }
}
