<?php

namespace App\Policies;

use App\Models\Recording;
use App\Models\User;

class RecordingPolicy
{
    /**
     * Determine whether the user can view any recordings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'lead', 'qa', 'readonly');
    }

    /**
     * Determine whether the user can view a recording.
     */
    public function view(User $user, Recording $recording): bool
    {
        return $this->viewAny($user);
    }
}
