<?php

namespace App\Services\Recordings;

use App\Repositories\Contracts\RecordingsRepositoryInterface;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class RecordingLibraryService
{
    public function __construct(
        private readonly RecordingsRepositoryInterface $recordings,
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->recordings->paginateLibrary($filters, $perPage);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function stream(array $filters, int $chunkSize = 500): LazyCollection
    {
        return $this->recordings->lazyLibrary($filters, $chunkSize);
    }

    public function agents(): Collection
    {
        return $this->recordings->agentsForLibrary();
    }

    public function piiMaskingEnabled(): bool
    {
        return (bool) $this->settings->get('privacy.pii_masking', true);
    }
}
