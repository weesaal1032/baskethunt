<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

interface CallsRepositoryInterface extends RepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateWithFilters(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $filters
     */
    public function lazyForExport(array $filters, int $chunkSize = 500): LazyCollection;
}
