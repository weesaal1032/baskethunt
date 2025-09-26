<?php

namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    public function all(): iterable;

    public function find(int|string $id): mixed;

    public function create(array $attributes): mixed;

    public function update(int|string $id, array $attributes): bool;

    public function delete(int|string $id): bool;
}
