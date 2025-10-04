<?php

namespace App\Providers;

use App\Models\Call;
use App\Models\QaScore;
use App\Models\Recording;
use App\Models\Transcript;
use App\Policies\CallPolicy;
use App\Policies\QaScorePolicy;
use App\Policies\RecordingPolicy;
use App\Policies\TranscriptPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Call::class => CallPolicy::class,
        Recording::class => RecordingPolicy::class,
        Transcript::class => TranscriptPolicy::class,
        QaScore::class => QaScorePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }
}
