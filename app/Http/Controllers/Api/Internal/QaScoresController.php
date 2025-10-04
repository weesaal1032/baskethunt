<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Services\Qa\QaReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class QaScoresController extends Controller
{
    public function __construct(private readonly QaReportingService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $input = $request->all();

        $validated = Validator::make($input, [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'team' => ['nullable', 'string', 'max:191'],
            'queue' => ['nullable', 'string', 'max:191'],
            'passed' => ['nullable', 'in:passed,failed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ])->validate();

        $filters = [
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'agent_id' => $validated['agent_id'] ?? null,
            'team' => $validated['team'] ?? null,
            'queue' => $validated['queue'] ?? null,
            'passed' => $validated['passed'] ?? null,
        ];

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : max(1, min((int) $request->integer('per_page', 100), 500));
        $paginator = $this->reports->paginateScores($filters, $perPage);

        $collection = $paginator->getCollection()->map(function ($score) {
            $submittedAt = $score->submitted_at ?? $score->updated_at ?? $score->created_at;
            $startedAt = $score->started_at ?? null;

            return [
                'id' => $score->id,
                'call_id' => $score->call_id,
                'version' => $score->version,
                'status' => $score->status,
                'total_score' => $score->total_score,
                'possible_score' => $score->possible_score,
                'passed' => (bool) $score->passed,
                'submitted_at' => $submittedAt?->toIso8601String(),
                'call_started_at' => $startedAt?->toIso8601String(),
                'queue' => $score->queue,
                'direction' => $score->direction,
                'duration_sec' => $score->duration_sec,
                'agent' => [
                    'name' => $score->agent_name ?? 'Unassigned',
                    'team' => $score->agent_team ?? 'Unassigned',
                ],
                'scored_by' => $score->scorer_name,
                'tags' => $score->tags ?? [],
                'comments' => $score->comments,
            ];
        });

        $paginator->setCollection($collection);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'filters' => array_filter($filters, static fn ($value) => ! in_array($value, [null, ''], true)),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'previous' => $paginator->previousPageUrl(),
            ],
        ]);
    }
}
