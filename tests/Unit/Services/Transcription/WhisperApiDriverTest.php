<?php

namespace Tests\Unit\Services\Transcription;

use App\Services\Transcription\Drivers\WhisperApiDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhisperApiDriverTest extends TestCase
{

    public function test_transcribes_using_whisper_api(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'audio-');
        $this->assertIsString($path);
        file_put_contents($path, 'audio');

        Http::fake([
            'https://api.whisper.test/*' => Http::response([
                'text' => 'Hello world',
                'segments' => [
                    ['start' => 0.0, 'end' => 1.0, 'text' => 'Hello'],
                    ['start' => 1.0, 'end' => 2.0, 'text' => 'world', 'confidence' => 0.9],
                ],
            ]),
        ]);

        $driver = new WhisperApiDriver([
            'endpoint' => 'https://api.whisper.test/v1/audio/transcriptions',
            'timeout' => 10,
        ]);

        $result = $driver->transcribe($path, [
            'api_key' => 'token-123',
            'language' => 'en',
            'model' => 'whisper-1',
        ]);

        $this->assertSame('Hello world', $result->text);
        $this->assertCount(2, $result->segments);
        $this->assertSame('en', $result->language);
        $this->assertSame(0.9, $result->confidence);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.whisper.test/v1/audio/transcriptions'
                && $request->hasHeader('Authorization', 'Bearer token-123');
        });

        @unlink($path);
    }
}
