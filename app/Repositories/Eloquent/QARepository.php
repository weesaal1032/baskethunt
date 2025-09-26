<?php

namespace App\Repositories\Eloquent;

use App\Models\QaScore;
use App\Repositories\Contracts\QARepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class QARepository extends BaseRepository implements QARepositoryInterface
{
    public function nextVersionForCall(int $callId): int
    {
        $max = (int) (QaScore::query()
            ->where('call_id', $callId)
            ->max('version') ?? 0);

        return $max + 1;
    }

    public function draftForCall(int $callId, int $userId): ?QaScore
    {
        return QaScore::query()
            ->with('scorer:id,name')
            ->where('call_id', $callId)
            ->where('scored_by', $userId)
            ->where('status', 'draft')
            ->orderByDesc('updated_at')
            ->first();
    }

    public function saveDraft(int $callId, int $userId, array $attributes): QaScore
    {
        $draft = $this->draftForCall($callId, $userId);

        if ($draft !== null) {
            $draft->fill($attributes);
            $draft->save();

            return $draft->refresh();
        }

        return QaScore::query()->create(array_merge([
            'call_id' => $callId,
            'scored_by' => $userId,
        ], $attributes));
    }

    public function markSubmitted(QaScore $score, CarbonImmutable $submittedAt): QaScore
    {
        $score->status = 'submitted';
        $score->submitted_at = $submittedAt;
        $score->save();

        return $score->refresh();
    }

    public function historyForCall(int $callId, int $limit = 20): Collection
    {
        return QaScore::query()
            ->with('scorer:id,name')
            ->where('call_id', $callId)
            ->orderByDesc('version')
            ->orderByDesc($this->scoreDateExpression())
            ->limit($limit)
            ->get();
    }

    public function recentTags(int $limit = 25): array
    {
        return QaScore::query()
            ->where('status', 'submitted')
            ->whereNotNull('tags')
            ->orderByDesc($this->scoreDateExpression())
            ->limit($limit * 4)
            ->get()
            ->flatMap(fn (QaScore $score) => collect($score->tags ?? []))
            ->filter()
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->take($limit)
            ->all();
    }

    public function agentSummaries(CarbonImmutable $from, CarbonImmutable $to, ?string $team = null): Collection
    {
        $bounds = $this->timelineBounds($from, $to);

        $query = QaScore::query()
            ->selectRaw('calls.agent_id, users.name as agent_name, COALESCE(users.team, "Unassigned") as team_name, COUNT(*) as evaluations, AVG(qa_scores.total_score) as average_score, SUM(CASE WHEN qa_scores.passed = 1 THEN 1 ELSE 0 END) as pass_count')
            ->join('calls', 'calls.id', '=', 'qa_scores.call_id')
            ->leftJoin('users', 'users.id', '=', 'calls.agent_id')
            ->where('qa_scores.status', 'submitted')
            ->whereBetween($this->scoreDateExpression(), $bounds)
            ->whereNotNull('calls.agent_id')
            ->groupBy('calls.agent_id', 'users.name', 'users.team')
            ->orderByDesc('average_score');

        if ($team !== null && $team !== '') {
            $query->where('users.team', $team);
        }

        return $query->get()->map(function ($row) {
            $evaluations = (int) $row->evaluations;
            $passCount = (int) $row->pass_count;

            return [
                'agent_id' => (int) $row->agent_id,
                'agent_name' => $row->agent_name ?? 'Unassigned',
                'team' => $row->team_name ?? 'Unassigned',
                'evaluations' => $evaluations,
                'average_score' => round((float) $row->average_score, 2),
                'pass_rate' => $evaluations > 0 ? round(($passCount / $evaluations) * 100, 2) : 0.0,
            ];
        });
    }

    public function teamSummaries(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $bounds = $this->timelineBounds($from, $to);

        return QaScore::query()
            ->selectRaw('COALESCE(users.team, "Unassigned") as team_name, COUNT(*) as evaluations, AVG(qa_scores.total_score) as average_score, SUM(CASE WHEN qa_scores.passed = 1 THEN 1 ELSE 0 END) as pass_count')
            ->join('calls', 'calls.id', '=', 'qa_scores.call_id')
            ->leftJoin('users', 'users.id', '=', 'calls.agent_id')
            ->where('qa_scores.status', 'submitted')
            ->whereBetween($this->scoreDateExpression(), $bounds)
            ->groupBy('team_name')
            ->orderByDesc('average_score')
            ->get()
            ->map(function ($row) {
                $evaluations = (int) $row->evaluations;
                $passCount = (int) $row->pass_count;

                return [
                    'team' => $row->team_name ?? 'Unassigned',
                    'evaluations' => $evaluations,
                    'average_score' => round((float) $row->average_score, 2),
                    'pass_rate' => $evaluations > 0 ? round(($passCount / $evaluations) * 100, 2) : 0.0,
                ];
            });
    }

    public function trendline(CarbonImmutable $from, CarbonImmutable $to, ?string $team = null, ?int $agentId = null): Collection
    {
        $bounds = $this->timelineBounds($from, $to);
        $dateExpression = DB::raw('DATE(' . $this->scoreDateExpression()->getValue(DB::connection()->getQueryGrammar()) . ')');

        $dateSql = $this->scoreDateExpressionSql();

        $query = QaScore::query()
            ->selectRaw('DATE(' . $dateSql . ') as period_date, AVG(qa_scores.total_score) as average_score, COUNT(*) as evaluations')
            ->join('calls', 'calls.id', '=', 'qa_scores.call_id')
            ->leftJoin('users', 'users.id', '=', 'calls.agent_id')
            ->where('qa_scores.status', 'submitted')
            ->whereBetween($this->scoreDateExpression(), $bounds)
            ->groupBy(DB::raw('DATE(' . $dateSql . ')'))
            ->orderBy(DB::raw('DATE(' . $dateSql . ')'));

        if ($team !== null && $team !== '') {
            $query->where('users.team', $team);
        }

        if ($agentId !== null) {
            $query->where('calls.agent_id', $agentId);
        }

        return $query->get()->map(function ($row) {
            return [
                'date' => CarbonImmutable::parse($row->period_date)->toDateString(),
                'average_score' => round((float) $row->average_score, 2),
                'evaluations' => (int) $row->evaluations,
            ];
        });
    }

    public function exportScores(CarbonImmutable $from, CarbonImmutable $to, ?int $agentId = null, ?string $team = null): LazyCollection
    {
        $bounds = $this->timelineBounds($from, $to);

        $query = QaScore::query()
            ->select([
                'qa_scores.version',
                'qa_scores.total_score',
                'qa_scores.possible_score',
                'qa_scores.passed',
                'qa_scores.status',
                'qa_scores.rubric_version',
                'qa_scores.tags',
                'qa_scores.comments',
                'qa_scores.score_breakdown',
                'qa_scores.submitted_at',
                'qa_scores.created_at',
                'calls.id as call_id',
                'calls.started_at',
                'calls.queue',
                'calls.direction',
                'calls.duration_sec',
                'agents.name as agent_name',
                'agents.team as agent_team',
                'scorers.name as scorer_name',
            ])
            ->join('calls', 'calls.id', '=', 'qa_scores.call_id')
            ->leftJoin('users as agents', 'agents.id', '=', 'calls.agent_id')
            ->leftJoin('users as scorers', 'scorers.id', '=', 'qa_scores.scored_by')
            ->where('qa_scores.status', 'submitted')
            ->whereBetween($this->scoreDateExpression(), $bounds)
            ->orderByDesc($this->scoreDateExpression());

        if ($agentId !== null) {
            $query->where('calls.agent_id', $agentId);
        }

        if ($team !== null && $team !== '') {
            $query->where('agents.team', $team);
        }

        return $query->lazy();
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function timelineBounds(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function scoreDateExpression(): Expression
    {
        return DB::raw($this->scoreDateExpressionSql());
    }

    private function scoreDateExpressionSql(): string
    {
        return 'COALESCE(qa_scores.submitted_at, qa_scores.updated_at, qa_scores.created_at)';
    }
}
