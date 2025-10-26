<?php

namespace App\Repositories\Contracts;

use App\Models\QaScore;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

interface QARepositoryInterface extends RepositoryInterface
{
    public function nextVersionForCall(int $callId): int;

    public function draftForCall(int $callId, int $userId): ?QaScore;

    /**
     * @param array<string, mixed> $attributes
     */
    public function saveDraft(int $callId, int $userId, array $attributes): QaScore;

    public function markSubmitted(QaScore $score, CarbonImmutable $submittedAt): QaScore;

    /**
     * @return Collection<int, QaScore>
     */
    public function historyForCall(int $callId, int $limit = 20): Collection;

    /**
     * @return array<int, string>
     */
    public function recentTags(int $limit = 25): array;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function agentSummaries(CarbonImmutable $from, CarbonImmutable $to, ?string $team = null): Collection;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function teamSummaries(CarbonImmutable $from, CarbonImmutable $to): Collection;

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function trendline(CarbonImmutable $from, CarbonImmutable $to, ?string $team = null, ?int $agentId = null): Collection;

    public function exportScores(CarbonImmutable $from, CarbonImmutable $to, ?int $agentId = null, ?string $team = null): LazyCollection;

    /**
     * @param array<string, mixed> $filters
     */
    public function paginateScores(array $filters, int $perPage): LengthAwarePaginator;
}
