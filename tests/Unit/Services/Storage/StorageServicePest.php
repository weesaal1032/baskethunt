<?php

use App\Services\Settings\SettingsService;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Storage;
use Mockery;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('s3');
});

afterEach(function (): void {
    Mockery::close();
});

test('storage service stores recordings on configured disk', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')->with('storage.default')->andReturn('local');

    $service = new StorageService(app('filesystem'), $settings);

    $result = $service->storeRecording('CALL-123', 'audio');

    expect($result->disk)->toBe('local');
    Storage::disk('local')->assertExists($result->path);
});

test('storage service can migrate recordings between drivers', function () {
    $settings = Mockery::mock(SettingsService::class);
    $settings->shouldReceive('get')->andReturn('local');

    $service = new StorageService(app('filesystem'), $settings);

    $path = 'recordings/2024/05/21/CALL-123.mp3';
    Storage::disk('local')->put($path, 'payload');

    $migrated = $service->migrateRecording($path, 'local', 's3');

    expect($migrated->disk)->toBe('s3');
    Storage::disk('local')->assertMissing($path);
    Storage::disk('s3')->assertExists($path);
});
