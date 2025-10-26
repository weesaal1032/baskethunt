<?php

namespace Tests\Unit\Services\Storage;

use App\Services\Storage\Drivers\LocalStorageDriver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalStorageDriverTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();

        Carbon::setTestNow();
    }

    public function test_store_persists_recording_under_dated_path(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2024, 5, 21, 10));

        $driver = new LocalStorageDriver(Storage::disk('local'));
        $stored = $driver->store('CALL-12345', 'audio-binary');

        $expectedPath = 'recordings/2024/05/21/CALL-12345.mp3';

        $this->assertSame('local', $stored->disk);
        $this->assertSame($expectedPath, $stored->path);
        Storage::disk('local')->assertExists($expectedPath);
        $this->assertSame('audio-binary', $driver->read($expectedPath));
    }

    public function test_store_sanitizes_identifier_when_needed(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2024, 1, 2, 3));

        $driver = new LocalStorageDriver(Storage::disk('local'));
        $stored = $driver->store('Call ID #42', 'payload');

        $this->assertSame('recordings/2024/01/02/Call_ID__42.mp3', $stored->path);
        Storage::disk('local')->assertExists($stored->path);
    }
}
