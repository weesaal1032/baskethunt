<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use RuntimeException;

abstract class BaseRepository implements RepositoryInterface
{
    protected function notImplemented(): never
    {
        throw new RuntimeException('Repository implementation pending.');
    }

    public function all(): iterable
    {
        return [];
    }

    public function find(int|string $id): mixed
    {
        return $this->notImplemented();
    }

    public function create(array $attributes): mixed
    {
        return $this->notImplemented();
    }

    public function update(int|string $id, array $attributes): bool
    {
        return $this->notImplemented();
    }

    public function delete(int|string $id): bool
    {
        return $this->notImplemented();
    }
}
