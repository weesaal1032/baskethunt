<?php

namespace App\Repositories\Eloquent;

use App\Models\Call;
use App\Models\Recording;
use App\Models\User;
use App\Repositories\Contracts\RecordingsRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class RecordingsRepository extends BaseRepository implements RecordingsRepositoryInterface
{
    public function findForDetail(int $id): ?Recording
    {
        return Recording::query()
            ->with([
                'transcript',
                'call' => function (Builder $callQuery): void {
                    $callQuery
                        ->select([
                            'id',
                            'provider_call_id',
                            'provider_id',
                            'agent_id',
                            'from_number',
                            'to_number',
                            'direction',
                            'started_at',
                            'ended_at',
                            'duration_sec',
                            'disposition',
                            'queue',
                            'metadata',
                        ])
                        ->with([
                            'agent:id,name',
                            'provider:id,name',
                            'qaScores' => function (Builder $qaQuery): void {
                                $qaQuery
                                    ->select(['id', 'call_id', 'total_score', 'comments', 'created_at'])
                                    ->latest('created_at');
                            },
                        ]);
                },
            ])
            ->find($id);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginateLibrary(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->buildLibraryQuery($filters);

        return (clone $query)
            ->orderByDesc($this->callStartedAtSubquery())
            ->orderByDesc('recordings.id')
            ->paginate($perPage, ['*'], 'page')
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function lazyLibrary(array $filters, int $chunkSize = 500): LazyCollection
    {
        $query = $this->buildLibraryQuery($filters);

        return (clone $query)
            ->orderByDesc($this->callStartedAtSubquery())
            ->orderByDesc('recordings.id')
            ->lazy($chunkSize);
    }

    public function agentsForLibrary(): Collection
    {
        return User::query()
            ->select(['id', 'name'])
            ->whereIn('role', ['admin', 'lead', 'qa'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildLibraryQuery(array $filters): Builder
    {
        $query = Recording::query()
            ->with([
                'call' => function (Builder $callQuery): void {
                    $callQuery
                        ->select([
                            'id',
                            'provider_call_id',
                            'agent_id',
                            'from_number',
                            'to_number',
                            'direction',
                            'started_at',
                            'ended_at',
                            'duration_sec',
                            'disposition',
                            'queue',
                        ])
                        ->with([
                            'agent:id,name',
                            'qaScores' => function (Builder $qaQuery): void {
                                $qaQuery
                                    ->select(['id', 'call_id', 'total_score', 'created_at'])
                                    ->latest('created_at');
                            },
                        ]);
                },
                'transcript',
            ]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();

            $query->whereHas('call', function (Builder $callQuery) use ($from): void {
                $callQuery->where('started_at', '>=', $from);
            });
        }

        if (! empty($filters['date_to'])) {
            $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

            $query->whereHas('call', function (Builder $callQuery) use ($to): void {
                $callQuery->where('started_at', '<=', $to);
            });
        }

        if (! empty($filters['agent'])) {
            $query->whereHas('call', function (Builder $callQuery) use ($filters): void {
                $callQuery->where('agent_id', (int) $filters['agent']);
            });
        }

        if (! empty($filters['direction'])) {
            $query->whereHas('call', function (Builder $callQuery) use ($filters): void {
                $callQuery->where('direction', $filters['direction']);
            });
        }

        if (! empty($filters['number'])) {
            $needle = $this->like($filters['number']);

            $query->whereHas('call', function (Builder $callQuery) use ($needle): void {
                $callQuery->where(function (Builder $nested) use ($needle): void {
                    $nested
                        ->where('from_number', 'like', $needle)
                        ->orWhere('to_number', 'like', $needle);
                });
            });
        }

        if (! empty($filters['disposition'])) {
            $query->whereHas('call', function (Builder $callQuery) use ($filters): void {
                $callQuery->where('disposition', $filters['disposition']);
            });
        }

        if (! empty($filters['queue'])) {
            $query->whereHas('call', function (Builder $callQuery) use ($filters): void {
                $callQuery->where('queue', $filters['queue']);
            });
        }

        if (! empty($filters['has_transcript'])) {
            if ($filters['has_transcript'] === 'with') {
                $query->whereHas('transcript', function (Builder $transcriptQuery): void {
                    $transcriptQuery->where('status', 'ready');
                });
            } elseif ($filters['has_transcript'] === 'without') {
                $query->whereDoesntHave('transcript', function (Builder $transcriptQuery): void {
                    $transcriptQuery->where('status', 'ready');
                });
            }
        }

        if (! empty($filters['has_qa_score'])) {
            if ($filters['has_qa_score'] === 'with') {
                $query->whereHas('call.qaScores');
            } elseif ($filters['has_qa_score'] === 'without') {
                $query->whereDoesntHave('call.qaScores');
            }
        }
    }

    private function like(string $value): string
    {
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $value);

        return '%' . $escaped . '%';
    }

    private function callStartedAtSubquery(): Builder
    {
        return Call::query()
            ->select('started_at')
            ->whereColumn('calls.id', 'recordings.call_id')
            ->limit(1);
    }

    public function retentionCandidates(CarbonImmutable $cutoff): LazyCollection
    {
        return Recording::query()
            ->with([
                'transcript' => fn (Builder $query) => $query->withTrashed(),
                'call:id,provider_call_id,started_at',
            ])
            ->whereNull('deleted_at')
            ->whereHas('call', function (Builder $callQuery) use ($cutoff): void {
                $callQuery->where('started_at', '<', $cutoff);
            })
            ->orderBy('id')
            ->lazy();
    }

    public function purgeCandidates(CarbonImmutable $purgeBefore): LazyCollection
    {
        return Recording::onlyTrashed()
            ->with([
                'transcript' => fn (Builder $query) => $query->withTrashed(),
                'call:id,provider_call_id,started_at',
            ])
            ->where('deleted_at', '<=', $purgeBefore)
            ->orderBy('id')
            ->lazy();
    }
}
