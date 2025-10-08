<?php

use App\Jobs\TranscribeRecordingJob;
use App\Models\Call;
use App\Models\Recording;
use App\Services\Settings\SettingsService;
use App\Services\Transcription\TranscriptionResult;
use App\Services\Transcription\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Mockery::close();
});

test('transcribe recording job persists transcript output', function () {
    $call = Call::factory()->create(['duration_sec' => 120]);
    $recording = Recording::factory()->create([
        'call_id' => $call->id,
        'status' => 'ready',
        'storage_backend' => 'local',
        'local_path' => 'recordings/test.mp3',
    ]);

    $result = new TranscriptionResult('hello world', [
        ['start' => 0.0, 'end' => 1.0, 'text' => 'hello'],
        ['start' => 1.0, 'end' => 2.0, 'text' => 'world'],
    ], 0.9, 'en');

    $service = Mockery::mock(TranscriptionService::class);
    $service->shouldReceive('transcribe')
        ->once()
        ->andReturn($result);

    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')->andReturnNull();

    $job = new TranscribeRecordingJob($recording->id, 'whisper_api');
    $job->handle($service, $settings);

    $recording->refresh();
    $transcript = $recording->transcript;

    expect($transcript)
        ->not->toBeNull()
        ->and($transcript->text)->toBe('hello world')
        ->and($transcript->segments)->toHaveCount(2)
        ->and($transcript->status)->toBe('ready');
});
