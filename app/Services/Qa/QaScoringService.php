<?php

namespace App\Services\Qa;

use App\Models\Call;
use App\Models\QaScore;
use App\Models\User;
use App\Repositories\Contracts\QARepositoryInterface;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class QaScoringService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly QARepositoryInterface $repository
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rubric(): array
    {
        $rubric = $this->settings->get('qa.rubric');

        if (! is_array($rubric) || $rubric === []) {
            $rubric = config('callhub.qa.rubric', []);
        }

        return $this->normaliseRubric($rubric);
    }

    public function passThreshold(): int
    {
        $threshold = (int) ($this->settings->get('qa.pass_threshold') ?? config('callhub.qa.pass_threshold', 80));

        return max(0, min(100, $threshold));
    }

    public function rubricVersion(): int
    {
        return (int) ($this->settings->get('qa.rubric_version') ?? config('callhub.qa.rubric_version', 1));
    }

    /**
     * @return array<string, mixed>
     */
    public function workspace(Call $call, User $user): array
    {
        $rubric = $this->rubric();
        $history = $this->repository->historyForCall($call->id);
        $draft = $this->repository->draftForCall($call->id, $user->id);
        $latestSubmitted = $history->firstWhere('status', 'submitted');
        $suggestedTags = $this->repository->recentTags();
        $maxVersion = (int) ($history->max(fn (QaScore $score) => $score->version) ?? 0);
        $nextVersion = $draft?->version ?? ($maxVersion + 1);

        $seedResponses = $draft?->responses ?? $latestSubmitted?->responses ?? [];
        $seedComment = $draft?->comments ?? $latestSubmitted?->comments ?? '';
        $seedTags = $draft?->tags ?? $latestSubmitted?->tags ?? [];

        return [
            'rubric' => $rubric,
            'rubric_version' => $this->rubricVersion(),
            'pass_threshold' => $this->passThreshold(),
            'history' => $history,
            'draft' => $draft,
            'latest_submitted' => $latestSubmitted,
            'suggested_tags' => $suggestedTags,
            'next_version' => max(1, $nextVersion),
            'seed' => [
                'responses' => $seedResponses,
                'comment' => $seedComment,
                'tags' => $seedTags,
                'passed' => $draft?->passed ?? $latestSubmitted?->passed ?? false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveDraft(Call $call, User $user, array $payload): QaScore
    {
        $rubric = $this->rubric();
        $version = $this->draftVersion($call->id, $payload['version'] ?? null);
        $rubricVersion = (int) ($payload['rubric_version'] ?? $this->rubricVersion());

        $responses = $this->sanitizeResponses($rubric, $payload['responses'] ?? []);
        $comment = $this->sanitizeComment($payload['comment'] ?? null);
        $tags = $this->sanitizeTags($payload['tags'] ?? []);
        $calc = $this->calculateScore($rubric, $responses);

        $passed = $this->shouldPass($payload['passed'] ?? null, $calc['percent']);

        return $this->repository->saveDraft($call->id, $user->id, [
            'version' => $version,
            'status' => 'draft',
            'rubric_version' => $rubricVersion,
            'rubric' => $rubric,
            'responses' => $responses,
            'score_breakdown' => $calc['breakdown'],
            'possible_score' => $calc['possible'],
            'total_score' => $calc['score'],
            'passed' => $passed,
            'comments' => $comment,
            'tags' => $tags,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function submit(Call $call, User $user, array $payload): QaScore
    {
        $draft = $this->saveDraft($call, $user, $payload);

        return $this->repository->markSubmitted($draft, CarbonImmutable::now());
    }

    /**
     * @param array<int, array<string, mixed>> $rubric
     * @param array<string, mixed> $responses
     * @return array{possible:int, score:int, percent:float, breakdown:array<string, mixed>}
     */
    public function calculateScore(array $rubric, array $responses): array
    {
        $possible = 0.0;
        $earned = 0.0;
        $breakdown = [];

        foreach ($rubric as $category) {
            $categoryId = (string) ($category['id'] ?? Str::uuid()->toString());
            $categoryWeight = (float) ($category['weight'] ?? 0);
            $categoryEarned = 0.0;
            $categoryPossible = 0.0;

            foreach ($category['questions'] ?? [] as $question) {
                $questionId = (string) ($question['id'] ?? Str::uuid()->toString());
                $type = (string) ($question['type'] ?? 'yes_no');
                $weight = (float) ($question['weight'] ?? 0);
                $categoryPossible += $weight;

                $value = $responses[$questionId] ?? null;
                $questionScore = 0.0;

                if ($type === 'yes_no') {
                    $questionScore = $this->scoreYesNo($value, $weight);
                } elseif ($type === 'scale') {
                    $max = (float) ($question['scale_max'] ?? $weight);
                    $min = (float) ($question['scale_min'] ?? 0);
                    $questionScore = $this->scoreScale($value, $weight, $min, max($max, 1));
                }

                $categoryEarned += $questionScore;
                $breakdown[$questionId] = [
                    'earned' => round($questionScore, 2),
                    'possible' => round($weight, 2),
                ];
            }

            if ($categoryPossible <= 0 && $categoryWeight > 0) {
                $categoryPossible = $categoryWeight;
            }

            $possible += $categoryPossible;
            $earned += $categoryEarned;
        }

        if ($possible <= 0) {
            return [
                'possible' => 0,
                'score' => 0,
                'percent' => 0.0,
                'breakdown' => $breakdown,
            ];
        }

        $percent = ($earned / $possible) * 100;

        return [
            'possible' => (int) round($possible),
            'score' => (int) round($percent),
            'percent' => round($percent, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rubric
     * @param array<string, mixed> $responses
     * @return array<string, mixed>
     */
    private function sanitizeResponses(array $rubric, array $responses): array
    {
        $allowedKeys = collect($rubric)
            ->flatMap(fn ($category) => collect($category['questions'] ?? [])->pluck('id')->filter())
            ->map(fn ($id) => (string) $id)
            ->all();

        $sanitised = [];

        foreach ($allowedKeys as $questionId) {
            if (array_key_exists($questionId, $responses)) {
                $sanitised[$questionId] = $responses[$questionId];
            }
        }

        return $sanitised;
    }

    private function sanitizeComment(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }

        $comment = trim($comment);

        return $comment === '' ? null : Str::limit($comment, 1000, '…');
    }

    /**
     * @param mixed $passedOverride
     */
    private function shouldPass(mixed $passedOverride, float $percent): bool
    {
        if ($passedOverride !== null) {
            return filter_var($passedOverride, FILTER_VALIDATE_BOOLEAN);
        }

        return $percent >= $this->passThreshold();
    }

    /**
     * @param array<int, array<string, mixed>> $rubric
     */
    private function normaliseRubric(array $rubric): array
    {
        return collect($rubric)
            ->map(function (array $category): array {
                $category['id'] = (string) ($category['id'] ?? Str::uuid()->toString());
                $category['name'] = (string) ($category['name'] ?? 'Category');
                $category['weight'] = (float) ($category['weight'] ?? 0);
                $category['questions'] = collect($category['questions'] ?? [])
                    ->map(function (array $question): array {
                        $question['id'] = (string) ($question['id'] ?? Str::uuid()->toString());
                        $question['prompt'] = (string) ($question['prompt'] ?? 'Question');
                        $question['type'] = in_array($question['type'] ?? 'yes_no', ['yes_no', 'scale'], true)
                            ? $question['type']
                            : 'yes_no';
                        $question['weight'] = (float) ($question['weight'] ?? 0);
                        if ($question['type'] === 'scale') {
                            $question['scale_min'] = (float) ($question['scale_min'] ?? 0);
                            $question['scale_max'] = max((float) ($question['scale_max'] ?? 5), 1);
                        }

                        return $question;
                    })
                    ->values()
                    ->all();

                return $category;
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $tags
     * @return array<int, string>
     */
    private function sanitizeTags(array $tags): array
    {
        return collect($tags)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($tag) => Str::limit($tag, 64, ''))
            ->all();
    }

    private function draftVersion(int $callId, mixed $requestedVersion): int
    {
        if (is_numeric($requestedVersion) && (int) $requestedVersion > 0) {
            return (int) $requestedVersion;
        }

        return $this->repository->nextVersionForCall($callId);
    }

    private function scoreYesNo(mixed $value, float $weight): float
    {
        if ($weight <= 0) {
            return 0.0;
        }

        $truthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $truthy ? $weight : 0.0;
    }

    private function scoreScale(mixed $value, float $weight, float $min, float $max): float
    {
        if ($weight <= 0) {
            return 0.0;
        }

        $numeric = is_numeric($value) ? (float) $value : $min;
        $numeric = max($min, min($numeric, $max));
        $range = max($max - $min, 1);

        return $weight * (($numeric - $min) / $range);
    }
}
