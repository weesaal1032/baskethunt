<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\Recording;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RecordingFactory extends Factory
{
    protected $model = Recording::class;

    public function definition(): array
    {
        $call = Call::factory();
        $path = sprintf('recordings/%s.mp3', Str::uuid());

        return [
            'call_id' => $call,
            'remote_url' => $this->faker->url(),
            'local_path' => $path,
            'storage_backend' => 'local',
            'format' => 'mp3',
            'bytes' => $this->faker->numberBetween(1024, 2048),
            'checksum' => hash('sha256', Str::random()),
            'waveform_json' => [1, 0.5, 0.2],
            'status' => 'ready',
        ];
    }
}
