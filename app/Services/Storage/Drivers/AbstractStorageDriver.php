<?php

namespace App\Services\Storage\Drivers;

use App\Services\Storage\StoredRecording;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractStorageDriver implements StorageDriver
{
    public function __construct(protected readonly FilesystemAdapter $disk)
    {
    }

    abstract public function name(): string;

    public function store(string $providerCallId, $contents, string $extension = 'mp3'): StoredRecording
    {
        $extension = $this->normalizeExtension($extension);
        $path = $this->buildPath($providerCallId, $extension);

        $this->write($path, $contents);

        return new StoredRecording($this->name(), $path);
    }

    public function write(string $path, $contents): void
    {
        if (! \is_string($contents) && ! \is_resource($contents)) {
            throw new InvalidArgumentException('Storage contents must be a string or stream resource.');
        }

        if (\is_resource($contents)) {
            rewind($contents);
        }

        $this->disk->put($path, $contents, $this->writeOptions());
    }

    public function read(string $path): string
    {
        return $this->disk->get($path);
    }

    public function readStream(string $path)
    {
        $stream = $this->disk->readStream($path);

        if ($stream === false || $stream === null) {
            throw new RuntimeException(sprintf('Unable to open stream for [%s] on [%s] disk.', $path, $this->name()));
        }

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->disk->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    protected function buildPath(string $providerCallId, string $extension): string
    {
        $datePath = Carbon::now()->format('Y/m/d');
        $filename = $this->sanitizeIdentifier($providerCallId).'.'.$extension;

        return sprintf('recordings/%s/%s', $datePath, $filename);
    }

    protected function sanitizeIdentifier(string $identifier): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9\-_.]/', '_', $identifier) ?? '';
        $sanitized = trim($sanitized, '_');

        return $sanitized !== '' ? $sanitized : 'recording';
    }

    protected function normalizeExtension(string $extension): string
    {
        $extension = trim($extension);
        $extension = $extension === '' ? 'mp3' : ltrim($extension, '.');

        if ($extension === '') {
            return 'mp3';
        }

        return $extension;
    }

    /**
     * @return array<string, mixed>
     */
    protected function writeOptions(): array
    {
        return ['visibility' => 'private'];
    }
}
