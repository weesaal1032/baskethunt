<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Telephony',
            'base_url' => $this->faker->url(),
            'auth_type' => 'header',
            'api_key' => Str::random(32),
            'rate_limit_json' => [
                'requests_per_minute' => 60,
                'burst' => 10,
            ],
        ];
    }
}
