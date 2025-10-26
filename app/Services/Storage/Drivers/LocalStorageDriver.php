<?php

namespace App\Services\Storage\Drivers;

use DateTimeInterface;

class LocalStorageDriver extends AbstractStorageDriver
{
    public function name(): string
    {
        return 'local';
    }

    public function temporaryUrl(string $path, DateTimeInterface $expiresAt): string
    {
        $token = hash('sha256', $path.'|'.$expiresAt->getTimestamp());

        return sprintf('local://%s?token=%s', ltrim($path, '/'), $token);
    }
}
