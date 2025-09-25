<?php

namespace App\Repositories\Contracts;

use App\Models\Recording;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface RecordingsRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateLibrary(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function lazyLibrary(array $filters, int $chunkSize = 500): LazyCollection;

    public function agentsForLibrary(): Collection;

    public function findForDetail(int $id): ?Recording;
}
