<?php

namespace App\Policies;

use App\Models\Transcript;
use App\Models\User;

class TranscriptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'lead', 'qa', 'readonly');
    }

    public function view(User $user, Transcript $transcript): bool
    {
        return $this->viewAny($user);
    }
}
