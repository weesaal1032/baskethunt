<?php

namespace App\Services\Qa;

use App\Repositories\Contracts\QARepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class QaReportingService
{
    public function __construct(
        private readonly QARepositoryInterface $repository,
        private readonly QaScoringService $scoring
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(?string $from, ?string $to, ?string $team = null): array
    {
        [$fromDate, $toDate] = $this->resolveRange($from, $to);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'team' => $team,
            'agents' => $this->repository->agentSummaries($fromDate, $toDate, $team),
            'teams' => $this->repository->teamSummaries($fromDate, $toDate),
            'trendline' => $this->repository->trendline($fromDate, $toDate, $team),
            'pass_threshold' => $this->scoring->passThreshold(),
        ];
    }

    public function agentTrend(int $agentId, ?string $from, ?string $to): Collection
    {
        [$fromDate, $toDate] = $this->resolveRange($from, $to);

        return $this->repository->trendline($fromDate, $toDate, null, $agentId);
    }

    public function exportScores(?string $from, ?string $to, ?int $agentId = null, ?string $team = null): LazyCollection
    {
        [$fromDate, $toDate] = $this->resolveRange($from, $to);

        return $this->repository->exportScores($fromDate, $toDate, $agentId, $team);
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function resolveRange(?string $from, ?string $to): array
    {
        $end = $to ? CarbonImmutable::parse($to) : CarbonImmutable::now();
        $start = $from ? CarbonImmutable::parse($from) : $end->subWeeks(4);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay(), $end->endOfDay()];
    }
}
