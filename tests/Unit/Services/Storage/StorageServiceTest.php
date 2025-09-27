<?php

namespace Tests\Unit\Services\Storage;

use App\Services\Settings\SettingsService;
use App\Services\Storage\StorageService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class StorageServiceTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }

    public function test_migrate_moves_recording_between_drivers(): void
    {
        Storage::fake('local');
        Storage::fake('s3');

        $path = 'recordings/2024/05/21/CALL-99999.mp3';
        Storage::disk('local')->put($path, 'original');

        $settings = Mockery::mock(SettingsService::class);
        $settings->shouldReceive('get')->andReturn('local');

        $service = new StorageService(app('filesystem'), $settings);

        $result = $service->migrateRecording($path, 'local', 's3');

        Storage::disk('local')->assertMissing($path);
        Storage::disk('s3')->assertExists($path);
        $this->assertSame('s3', $result->disk);
        $this->assertSame($path, $result->path);
        $this->assertSame('original', Storage::disk('s3')->get($path));
    }
}
