<?php

namespace App\Services\Storage;

class StoredRecording
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path
    ) {
    }

    public function filename(): string
    {
        return basename($this->path);
    }

    public function directory(): string
    {
        $directory = dirname($this->path);

        return $directory === '.' ? '' : $directory;
    }

    /**
     * @return array{disk: string, path: string}
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
        ];
    }
}
