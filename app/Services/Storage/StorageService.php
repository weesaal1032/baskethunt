<?php

namespace App\Services\Storage;

use App\Services\Settings\SettingsService;
use App\Services\Storage\Drivers\LocalStorageDriver;
use App\Services\Storage\Drivers\S3StorageDriver;
use App\Services\Storage\Drivers\StorageDriver;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;
use RuntimeException;

class StorageService
{
    /**
     * @var array<string, StorageDriver>
     */
    private array $drivers = [];

    public function __construct(
        private readonly FilesystemManager $filesystem,
        private readonly SettingsService $settings
    ) {
    }

    /**
     * @param resource|string $contents
     */
    public function storeRecording(string $providerCallId, $contents, string $extension = 'mp3', ?string $driver = null): StoredRecording
    {
        $driverName = $driver ?? $this->resolveDefaultDriver();

        return $this->driver($driverName)->store($providerCallId, $contents, $extension);
    }

    public function retrieveRecording(string $path, ?string $driver = null): string
    {
        $driverName = $driver ?? $this->resolveDefaultDriver();

        return $this->driver($driverName)->read($path);
    }

    /**
     * @return resource
     */
    public function streamRecording(string $path, ?string $driver = null)
    {
        $driverName = $driver ?? $this->resolveDefaultDriver();

        return $this->driver($driverName)->readStream($path);
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, ?string $driver = null): string
    {
        $driverName = $driver ?? $this->resolveDefaultDriver();

        return $this->driver($driverName)->temporaryUrl($path, $expiresAt);
    }

    public function migrateRecording(string $path, string $fromDriver = 'local', string $toDriver = 's3', bool $deleteSource = true): StoredRecording
    {
        $source = $this->driver($fromDriver);
        $target = $this->driver($toDriver);

        if (! $source->exists($path)) {
            throw new RuntimeException(sprintf('Source path [%s] not found on [%s] driver.', $path, $fromDriver));
        }

        $stream = $source->readStream($path);

        try {
            $target->write($path, $stream);
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($deleteSource) {
            $source->delete($path);
        }

        return new StoredRecording($target->name(), $path);
    }

    private function resolveDefaultDriver(): string
    {
        $configured = $this->settings->get('storage.default');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return config('storage_backends.default', 'local');
    }

    private function driver(string $driver): StorageDriver
    {
        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = match ($driver) {
                'local' => new LocalStorageDriver($this->filesystem->disk('local')),
                's3' => new S3StorageDriver($this->filesystem->disk('s3')),
                default => throw new InvalidArgumentException(sprintf('Unsupported storage driver [%s]', $driver)),
            };
        }

        return $this->drivers[$driver];
    }
}
