<?php

namespace App\Support\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CallFilters
{
    /**
     * @param array<string, mixed> $input
     * @return array{
     *     date_from: string|null,
     *     date_to: string|null,
     *     agent: int|null,
     *     direction: string|null,
     *     number: string|null,
     *     disposition: string|null,
     *     queue: string|null,
     *     has_transcript: string|null,
     *     has_qa_score: string|null
     * }
     *
     * @throws ValidationException
     */
    public function normalise(array $input): array
    {
        $data = Validator::make(
            Arr::only($input, [
                'date_from',
                'date_to',
                'agent',
                'direction',
                'number',
                'disposition',
                'queue',
                'has_transcript',
                'has_qa_score',
            ]),
            $this->rules()
        )->validate();

        $from = isset($data['date_from']) && $data['date_from'] !== null
            ? CarbonImmutable::parse($data['date_from'])->startOfDay()
            : null;
        $to = isset($data['date_to']) && $data['date_to'] !== null
            ? CarbonImmutable::parse($data['date_to'])->endOfDay()
            : null;

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [
            'date_from' => $from?->toDateString(),
            'date_to' => $to?->toDateString(),
            'agent' => isset($data['agent']) ? (int) $data['agent'] : null,
            'direction' => $data['direction'] ?? null,
            'number' => $data['number'] ?? null,
            'disposition' => $data['disposition'] ?? null,
            'queue' => $data['queue'] ?? null,
            'has_transcript' => $data['has_transcript'] ?? null,
            'has_qa_score' => $data['has_qa_score'] ?? null,
        ];
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    private function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'agent' => ['nullable', 'integer', 'exists:users,id'],
            'direction' => ['nullable', 'in:inbound,outbound'],
            'number' => ['nullable', 'string', 'max:32'],
            'disposition' => ['nullable', 'string', 'max:191'],
            'queue' => ['nullable', 'string', 'max:191'],
            'has_transcript' => ['nullable', 'in:with,without'],
            'has_qa_score' => ['nullable', 'in:with,without'],
        ];
    }
}
