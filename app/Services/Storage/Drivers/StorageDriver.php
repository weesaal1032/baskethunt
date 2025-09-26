<?php

namespace App\Services\Storage\Drivers;

use App\Services\Storage\StoredRecording;
use DateTimeInterface;

interface StorageDriver
{
    public function name(): string;

    /**
     * @param resource|string $contents
     */
    public function store(string $providerCallId, $contents, string $extension = 'mp3'): StoredRecording;

    /**
     * @param resource|string $contents
     */
    public function write(string $path, $contents): void;

    public function read(string $path): string;

    /**
     * @return resource
     */
    public function readStream(string $path);

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt): string;
}
