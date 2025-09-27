<?php

namespace Tests\Unit\Services\Storage;

use App\Services\Storage\Drivers\S3StorageDriver;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class S3StorageDriverTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();

        Carbon::setTestNow();
        Mockery::close();
    }

    public function test_store_writes_private_object_with_expected_key(): void
    {
        Storage::fake('s3');
        Carbon::setTestNow(Carbon::create(2024, 5, 21, 10));

        $driver = new S3StorageDriver(Storage::disk('s3'));
        $stored = $driver->store('CALL-54321', 'binary');

        $expectedPath = 'recordings/2024/05/21/CALL-54321.mp3';

        $this->assertSame('s3', $stored->disk);
        $this->assertSame($expectedPath, $stored->path);
        Storage::disk('s3')->assertExists($expectedPath);
        $this->assertSame('binary', $driver->read($expectedPath));
    }

    public function test_temporary_url_uses_adapter_signed_urls(): void
    {
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $expiresAt = Carbon::now()->addMinutes(5);

        $adapter->shouldReceive('temporaryUrl')
            ->once()
            ->with('recordings/path.mp3', $expiresAt)
            ->andReturn('https://signed.example');

        $driver = new S3StorageDriver($adapter);

        $this->assertSame('https://signed.example', $driver->temporaryUrl('recordings/path.mp3', $expiresAt));
    }
}
