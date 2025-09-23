<?php

namespace Database\Seeders;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('CALLHUB_ADMIN_EMAIL', 'admin@example.com');

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'System Administrator',
                'password' => Hash::make(env('CALLHUB_ADMIN_PASSWORD', 'ChangeMe123!')),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        Provider::firstOrCreate(
            ['name' => 'Default Provider'],
            [
                'base_url' => 'https://api.example.com',
                'auth_type' => 'api_key',
                'api_key' => Str::random(32),
                'rate_limit_json' => [
                    'requests_per_minute' => 60,
                    'burst' => 10,
                ],
            ]
        );
    }
}
