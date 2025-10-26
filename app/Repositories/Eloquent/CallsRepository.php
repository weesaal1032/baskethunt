<?php

namespace App\Repositories\Eloquent;

use App\Models\Call;
use App\Repositories\Contracts\CallsRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

class CallsRepository extends BaseRepository implements CallsRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginateWithFilters(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->applyFilters($filters)
            ->orderByDesc('started_at')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function lazyForExport(array $filters, int $chunkSize = 500): LazyCollection
    {
        return $this->applyFilters($filters)
            ->orderByDesc('started_at')
            ->lazy($chunkSize);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(array $filters): Builder
    {
        $query = Call::query()
            ->with([
                'provider:id,name',
                'agent:id,name,team',
                'recordings' => fn ($recordings) => $recordings
                    ->with(['transcript' => fn ($transcript) => $transcript->whereNull('transcripts.deleted_at')])
                    ->orderByDesc('updated_at'),
                'qaScores' => fn ($scores) => $scores->orderByDesc('version'),
            ]);

        $from = isset($filters['date_from']) && $filters['date_from'] !== null
            ? CarbonImmutable::parse($filters['date_from'])->startOfDay()
            : null;
        $to = isset($filters['date_to']) && $filters['date_to'] !== null
            ? CarbonImmutable::parse($filters['date_to'])->endOfDay()
            : null;

        if ($from !== null) {
            $query->where('started_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('started_at', '<=', $to);
        }

        if (! empty($filters['agent'])) {
            $query->where('agent_id', (int) $filters['agent']);
        }

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (! empty($filters['number'])) {
            $number = $filters['number'];
            $query->where(function (Builder $builder) use ($number): void {
                $builder
                    ->where('from_number', 'like', "%{$number}%")
                    ->orWhere('to_number', 'like', "%{$number}%");
            });
        }

        if (! empty($filters['disposition'])) {
            $query->where('disposition', $filters['disposition']);
        }

        if (! empty($filters['queue'])) {
            $query->where('queue', $filters['queue']);
        }

        if (($filters['has_transcript'] ?? null) === 'with') {
            $query->whereHas('recordings.transcript', function (Builder $builder): void {
                $builder->whereNull('transcripts.deleted_at');
            });
        } elseif (($filters['has_transcript'] ?? null) === 'without') {
            $query->whereDoesntHave('recordings.transcript', function (Builder $builder): void {
                $builder->whereNull('transcripts.deleted_at');
            });
        }

        if (($filters['has_qa_score'] ?? null) === 'with') {
            $query->whereHas('qaScores', function (Builder $builder): void {
                $builder->where('status', 'submitted');
            });
        } elseif (($filters['has_qa_score'] ?? null) === 'without') {
            $query->whereDoesntHave('qaScores', function (Builder $builder): void {
                $builder->where('status', 'submitted');
            });
        }

        return $query;
    }
}
