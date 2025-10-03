<?php

namespace Tests\Feature\Console;

use App\Models\Audit;
use App\Models\Call;
use App\Models\Provider;
use App\Models\Recording;
use App\Models\Transcript;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnforceRetentionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_command_soft_deletes_and_purges_assets(): void
    {
        Storage::fake('local');

        User::factory()->create(['role' => 'admin']);
        $provider = Provider::factory()->create();

        $call = Call::query()->create([
            'provider_call_id' => 'provider-123',
            'provider_id' => $provider->id,
            'direction' => 'inbound',
            'from_number' => '+15550000001',
            'to_number' => '+15550000099',
            'agent_id' => null,
            'started_at' => CarbonImmutable::now()->subMonths(2),
            'ended_at' => CarbonImmutable::now()->subMonths(2)->addMinutes(5),
            'duration_sec' => 300,
            'disposition' => 'completed',
            'queue' => 'support',
            'metadata' => [],
        ]);

        $recording = Recording::query()->create([
            'call_id' => $call->id,
            'remote_url' => 'https://example.com/original.mp3',
            'local_path' => 'recordings/provider-123.mp3',
            'storage_backend' => 'local',
            'format' => 'mp3',
            'bytes' => 1024,
            'checksum' => 'abc123',
            'waveform_json' => null,
            'status' => 'ready',
        ]);

        $transcript = Transcript::query()->create([
            'recording_id' => $recording->id,
            'engine' => 'whisper_api',
            'language' => 'en',
            'text' => 'hello world',
            'confidence' => 0.9,
            'segments' => [],
            'status' => 'ready',
        ]);

        Storage::disk('local')->put($recording->local_path, 'audio');

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->setMany([
            'privacy.retention_months' => 1,
            'privacy.deletion_grace_days' => 1,
        ]);

        Log::spy();
        Artisan::call('privacy:enforce-retention', ['--dry-run' => true]);

        $this->assertFalse(Recording::withTrashed()->find($recording->id)?->trashed());
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) {
            return str_contains($message, 'Retention dry-run') && ($context['dry_run'] ?? false) === true;
        })->atLeast()->once();

        Log::spy();
        Artisan::call('privacy:enforce-retention');

        $this->assertSoftDeleted('recordings', ['id' => $recording->id]);
        $this->assertSoftDeleted('transcripts', ['id' => $transcript->id]);
        $this->assertGreaterThan(0, Audit::query()->where('action', 'recording.retention.soft_delete')->count());

        Recording::withTrashed()->whereKey($recording->id)->update(['deleted_at' => CarbonImmutable::now()->subDays(2)]);
        Transcript::withTrashed()->whereKey($transcript->id)->update(['deleted_at' => CarbonImmutable::now()->subDays(2)]);

        Log::spy();
        Artisan::call('privacy:enforce-retention');

        $this->assertDatabaseMissing('recordings', ['id' => $recording->id]);
        $this->assertDatabaseMissing('transcripts', ['id' => $transcript->id]);
        $this->assertFalse(Storage::disk('local')->exists('recordings/provider-123.mp3'));
        $this->assertGreaterThan(0, Audit::query()->where('action', 'recording.retention.purge')->count());
    }
}
