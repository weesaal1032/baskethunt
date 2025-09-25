<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QaScore;
use App\Models\Recording;
use App\Services\Qa\QaScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QaScoresController extends Controller
{
    public function __construct(private readonly QaScoringService $service)
    {
    }

    public function store(Request $request, Recording $recording): JsonResponse
    {
        $this->authorize('view', $recording);
        $this->authorize('create', QaScore::class);

        $call = $recording->call;
        abort_if($call === null, 422, 'Call context unavailable for recording.');

        $user = $request->user();
        abort_if($user === null, 403);

        $payload = $this->validatedPayload($request);
        $currentRubricVersion = $this->service->rubricVersion();

        if ((int) $payload['rubric_version'] !== $currentRubricVersion) {
            return response()->json([
                'message' => 'Rubric has changed. Refresh to load the latest version before scoring.',
                'rubric_version' => $currentRubricVersion,
            ], 409);
        }

        $score = $payload['finalize']
            ? $this->service->submit($call, $user, $payload)
            : $this->service->saveDraft($call, $user, $payload);

        $workspace = $this->service->workspace($call, $user);

        return response()->json([
            'score' => $this->transformScore($score),
            'history' => $workspace['history']->map(fn (QaScore $item) => $this->transformScore($item))->all(),
            'draft' => $workspace['draft'] ? $this->transformScore($workspace['draft']) : null,
            'latest_submitted' => $workspace['latest_submitted'] ? $this->transformScore($workspace['latest_submitted']) : null,
            'next_version' => $workspace['next_version'],
            'rubric_version' => $workspace['rubric_version'],
            'pass_threshold' => $workspace['pass_threshold'],
            'suggested_tags' => $workspace['suggested_tags'],
        ]);
    }

    public function history(Request $request, Recording $recording): JsonResponse
    {
        $this->authorize('view', $recording);

        $call = $recording->call;
        abort_if($call === null, 404);

        $user = $request->user();
        abort_if($user === null, 403);

        $workspace = $this->service->workspace($call, $user);

        return response()->json([
            'history' => $workspace['history']->map(fn (QaScore $score) => $this->transformScore($score))->all(),
            'draft' => $workspace['draft'] ? $this->transformScore($workspace['draft']) : null,
            'latest_submitted' => $workspace['latest_submitted'] ? $this->transformScore($workspace['latest_submitted']) : null,
            'suggested_tags' => $workspace['suggested_tags'],
            'rubric_version' => $workspace['rubric_version'],
            'pass_threshold' => $workspace['pass_threshold'],
            'next_version' => $workspace['next_version'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'responses' => ['required', 'array'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:64'],
            'passed' => ['nullable', 'boolean'],
            'version' => ['nullable', 'integer', 'min:1'],
            'rubric_version' => ['required', 'integer', 'min:1'],
            'finalize' => ['sometimes', 'boolean'],
        ]);

        $validated['finalize'] = (bool) ($validated['finalize'] ?? false);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformScore(QaScore $score): array
    {
        return [
            'id' => $score->id,
            'version' => $score->version,
            'status' => $score->status,
            'total_score' => $score->total_score,
            'possible_score' => $score->possible_score,
            'passed' => (bool) $score->passed,
            'comments' => $score->comments,
            'tags' => $score->tags ?? [],
            'rubric_version' => $score->rubric_version,
            'created_at' => optional($score->created_at)->toDateTimeString(),
            'updated_at' => optional($score->updated_at)->toDateTimeString(),
            'submitted_at' => optional($score->submitted_at)->toDateTimeString(),
            'scored_by' => $score->scorer?->only(['id', 'name']),
        ];
    }
}
