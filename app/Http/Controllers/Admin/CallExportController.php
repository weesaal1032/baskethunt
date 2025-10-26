<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Services\Calls\CallReportingService;
use App\Support\Filters\CallFilters;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CallExportController extends Controller
{
    public function __construct(
        private readonly CallReportingService $calls,
        private readonly CallFilters $filters
    ) {
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Call::class);

        $filters = $this->filters->normalise($request->all());
        $mask = $this->calls->piiMaskingEnabled();
        $stream = $this->calls->stream($filters);
        $filename = sprintf('calls-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($stream, $mask): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Call ID',
                'Provider Call ID',
                'Started At',
                'Ended At',
                'Direction',
                'From Number',
                'To Number',
                'Agent',
                'Disposition',
                'Queue',
                'Duration (sec)',
                'Recording Status',
                'Recording Backend',
                'Transcript Status',
                'QA Score',
                'QA Passed',
            ]);

            $stream->each(function (Call $call) use ($handle, $mask): void {
                $data = $this->calls->transform($call, $mask);
                $recording = $data['recording'] ?? null;
                $transcript = $data['transcript'] ?? null;
                $qa = $data['qa'] ?? null;

                fputcsv($handle, [
                    $data['id'],
                    $data['provider_call_id'],
                    $data['started_at'],
                    $data['ended_at'],
                    $data['direction'] ?? 'unknown',
                    $data['from_number'],
                    $data['to_number'],
                    $data['agent']['name'] ?? 'Unassigned',
                    $data['disposition'] ?? 'unknown',
                    $data['queue'] ?? '—',
                    $data['duration_sec'] ?? 0,
                    $recording['status'] ?? 'missing',
                    $recording['storage_backend'] ?? 'local',
                    $transcript['status'] ?? 'missing',
                    $qa['total_score'] ?? null,
                    isset($qa['passed']) ? ($qa['passed'] ? 'yes' : 'no') : 'no',
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
