<?php

namespace App\Services\Calls;

use App\Models\Call;
use App\Repositories\Contracts\CallsRepositoryInterface;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

final class CallReportingService
{
    public function __construct(
        private readonly CallsRepositoryInterface $calls,
        private readonly SettingsService $settings
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->calls->paginateWithFilters($filters, $perPage);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function stream(array $filters, int $chunkSize = 500): LazyCollection
    {
        return $this->calls->lazyForExport($filters, $chunkSize);
    }

    public function piiMaskingEnabled(): bool
    {
        return (bool) $this->settings->get('privacy.pii_masking', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(Call $call, bool $maskNumbers = false): array
    {
        $call->loadMissing(['agent:id,name,team', 'provider:id,name']);
        $recording = $call->recordings->sortByDesc('updated_at')->first();
        $transcript = $recording?->transcript;
        $latestScore = $call->qaScores
            ->where('status', 'submitted')
            ->sortByDesc(fn ($score) => $score->submitted_at ?? $score->updated_at ?? $score->created_at)
            ->first();

        $submittedAt = $latestScore?->submitted_at
            ?? $latestScore?->updated_at
            ?? $latestScore?->created_at;

        return [
            'id' => $call->id,
            'provider_call_id' => $call->provider_call_id,
            'provider' => [
                'id' => $call->provider_id,
                'name' => $call->provider?->name,
            ],
            'agent' => $call->agent ? [
                'id' => $call->agent->id,
                'name' => $call->agent->name,
                'team' => $call->agent->team,
            ] : null,
            'direction' => $call->direction,
            'queue' => $call->queue,
            'disposition' => $call->disposition,
            'from_number' => format_phone($call->from_number, $maskNumbers),
            'to_number' => format_phone($call->to_number, $maskNumbers),
            'duration_sec' => $call->duration_sec,
            'started_at' => $call->started_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
            'metadata' => $maskNumbers ? null : $call->metadata,
            'recording' => $recording ? [
                'id' => $recording->id,
                'status' => $recording->status,
                'storage_backend' => $recording->storage_backend,
                'format' => $recording->format,
                'bytes' => $recording->bytes,
                'checksum' => $recording->checksum,
            ] : null,
            'transcript' => $transcript ? [
                'id' => $transcript->id,
                'status' => $transcript->status,
                'language' => $transcript->language,
                'confidence' => $transcript->confidence,
                'updated_at' => $transcript->updated_at?->toIso8601String(),
            ] : null,
            'qa' => $latestScore ? [
                'id' => $latestScore->id,
                'version' => $latestScore->version,
                'total_score' => $latestScore->total_score,
                'possible_score' => $latestScore->possible_score,
                'passed' => (bool) $latestScore->passed,
                'submitted_at' => $submittedAt?->toIso8601String(),
                'tags' => $latestScore->tags ?? [],
            ] : null,
            'created_at' => $call->created_at?->toIso8601String(),
            'updated_at' => $call->updated_at?->toIso8601String(),
        ];
    }
}
