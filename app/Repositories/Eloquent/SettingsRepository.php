<?php

namespace App\Repositories\Eloquent;

use App\Models\Settings;
use App\Repositories\Contracts\SettingsRepositoryInterface;

class SettingsRepository extends BaseRepository implements SettingsRepositoryInterface
{
    public function __construct(private readonly Settings $model)
    {
    }

    public function findByKey(string $key): ?Settings
    {
        return $this->model->newQuery()->where('key', $key)->first();
    }

    public function upsert(string $key, ?string $value): void
    {
        $this->model->newQuery()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public function all(): iterable
    {
        return $this->model->newQuery()->get();
    }
}
