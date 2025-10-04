<?php

namespace App\Policies;

use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    /**
     * Determine whether the user can view any calls.
     */
    public function viewAny(User $user): bool
    {
        return $user->status === 'active' && $user->hasRole('admin', 'lead', 'qa', 'readonly');
    }

    /**
     * Determine whether the user can view a specific call.
     */
    public function view(User $user, Call $call): bool
    {
        return $this->viewAny($user);
    }
}
