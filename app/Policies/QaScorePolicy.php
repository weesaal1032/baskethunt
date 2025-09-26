<?php

namespace App\Policies;

use App\Models\QaScore;
use App\Models\User;

class QaScorePolicy
{
    protected array $managementRoles = ['admin', 'lead'];
    protected array $reviewRoles = ['admin', 'lead', 'qa'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(...$this->reviewRoles);
    }

    public function view(User $user, QaScore $score): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(...$this->reviewRoles);
    }

    public function update(User $user, QaScore $score): bool
    {
        return $user->hasRole(...$this->managementRoles) || $score->scored_by === $user->id;
    }
}
