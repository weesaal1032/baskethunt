<?php

namespace App\Services\Transcription\Drivers;

use App\Services\Transcription\TranscriptionResult;

interface TranscriptionDriver
{
    /**
     * @param array<string, mixed> $options
     */
    public function transcribe(string $filePath, array $options = []): TranscriptionResult;
}
