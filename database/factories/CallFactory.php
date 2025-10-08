<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-2 days', 'now');
        $duration = $this->faker->numberBetween(30, 1800);

        return [
            'provider_call_id' => Str::uuid()->toString(),
            'provider_id' => Provider::factory(),
            'agent_id' => User::factory(),
            'direction' => Arr::random(['inbound', 'outbound']),
            'from_number' => $this->faker->e164PhoneNumber(),
            'to_number' => $this->faker->e164PhoneNumber(),
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify("+{$duration} seconds"),
            'duration_sec' => $duration,
            'disposition' => Arr::random(['resolved', 'unresolved', 'voicemail']),
            'queue' => Arr::random(['support', 'sales', 'billing']),
            'metadata' => ['source' => 'factory'],
        ];
    }
}
