<?php

namespace App\Services\Storage\Drivers;

use DateTimeInterface;

class S3StorageDriver extends AbstractStorageDriver
{
    public function name(): string
    {
        return 's3';
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt): string
    {
        return $this->disk->temporaryUrl($path, $expiresAt);
    }
}
