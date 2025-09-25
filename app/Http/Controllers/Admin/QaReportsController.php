<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QaScore;
use App\Services\Qa\QaReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QaReportsController extends Controller
{
    public function __construct(private readonly QaReportingService $reports)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', QaScore::class);

        $summary = $this->reports->summary(
            $request->input('from'),
            $request->input('to'),
            $request->input('team')
        );

        $selectedAgent = $request->filled('agent_id') ? (int) $request->input('agent_id') : null;
        $agentTrend = $selectedAgent ? $this->reports->agentTrend($selectedAgent, $request->input('from'), $request->input('to')) : null;

        return view('layouts.base', [
            'title' => 'QA Reports',
            'slot' => view('admin.qa.reports', [
                'summary' => $summary,
                'filters' => [
                    'from' => $summary['from']->toDateString(),
                    'to' => $summary['to']->toDateString(),
                    'team' => $summary['team'],
                    'agent_id' => $selectedAgent,
                ],
                'agentTrend' => $agentTrend,
            ])->render(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', QaScore::class);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'agent_id' => ['nullable', 'integer'],
            'team' => ['nullable', 'string', 'max:191'],
        ]);

        $export = $this->reports->exportScores(
            $data['from'] ?? null,
            $data['to'] ?? null,
            $data['agent_id'] ?? null,
            $data['team'] ?? null
        );

        $filename = sprintf('qa-results-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($export): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Version',
                'Status',
                'Score (%)',
                'Passed',
                'Call ID',
                'Call Started At',
                'Agent',
                'Team',
                'Queue',
                'Direction',
                'Duration (sec)',
                'Scored By',
                'Submitted At',
                'Tags',
                'Comments',
            ]);

            $export->each(function ($row) use ($handle): void {
                $submittedAt = $row->submitted_at ?? $row->created_at;
                $startedAt = $row->started_at ?? null;
                $tags = is_array($row->tags) ? implode('|', $row->tags) : '';
                $comments = $row->comments ?? '';

                $startedAtFormatted = $startedAt instanceof \DateTimeInterface
                    ? $startedAt->toDateTimeString()
                    : ($startedAt ? (string) $startedAt : '');

                $submittedFormatted = $submittedAt instanceof \DateTimeInterface
                    ? $submittedAt->toDateTimeString()
                    : ($submittedAt ? (string) $submittedAt : '');

                fputcsv($handle, [
                    $row->version,
                    $row->status,
                    $row->total_score,
                    $row->passed ? 'yes' : 'no',
                    $row->call_id,
                    $startedAtFormatted,
                    $row->agent_name ?? 'Unassigned',
                    $row->agent_team ?? 'Unassigned',
                    $row->queue ?? '—',
                    $row->direction ?? '—',
                    $row->duration_sec ?? 0,
                    $row->scorer_name ?? '—',
                    $submittedFormatted,
                    $tags,
                    $comments,
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
