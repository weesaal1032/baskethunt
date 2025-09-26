<?php

namespace App\Services\Auth;

use App\Models\User;

class OtpService
{
    public function generate(User $user, int $ttlMinutes): array
    {
        $code = (string) random_int(100000, 999999);

        $token = $user->otpTokens()->create([
            'code_hash' => bcrypt($code),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return [$token, $code];
    }

    public function purgeActiveTokens(User $user): void
    {
        $user->otpTokens()
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->delete();
    }
}
