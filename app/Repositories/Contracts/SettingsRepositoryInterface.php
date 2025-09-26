<?php

namespace App\Repositories\Contracts;

interface SettingsRepositoryInterface extends RepositoryInterface
{
    public function findByKey(string $key): ?\App\Models\Settings;

    public function upsert(string $key, ?string $value): void;
}
