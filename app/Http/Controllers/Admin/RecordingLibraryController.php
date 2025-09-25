<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Services\Recordings\RecordingLibraryService;
use App\Services\Storage\StorageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RecordingLibraryController extends Controller
{
    public function __construct(
        private readonly RecordingLibraryService $service,
        private readonly StorageService $storage
    )
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Recording::class);

        $filters = $this->validatedFilters($request);
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;

        $recordings = $this->service->paginate($filters, $perPage);
        $agents = $this->service->agents();
        $piiMasking = $this->service->piiMaskingEnabled();

        return view('admin.recordings.index', [
            'recordings' => $recordings,
            'filters' => $filters,
            'agents' => $agents,
            'piiMasking' => $piiMasking,
            'perPage' => $perPage,
        ]);
    }

    public function show(Recording $recording): View
    {
        $this->authorize('view', $recording);

        $detail = $this->service->detail($recording->id);

        abort_if($detail === null, 404);

        $piiMasking = $this->service->piiMaskingEnabled();
        $audioUrl = URL::temporarySignedRoute(
            'admin.recordings.audio',
            CarbonImmutable::now()->addMinutes(5),
            ['recording' => $recording->id]
        );

        return view('admin.recordings.show', [
            'recording' => $detail,
            'call' => $detail->call,
            'transcript' => $detail->transcript,
            'piiMasking' => $piiMasking,
            'audioUrl' => $audioUrl,
        ]);
    }

    public function audio(Recording $recording): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('view', $recording);

        if ($recording->status !== 'ready' || $recording->local_path === null) {
            abort(404);
        }

        if ($recording->storage_backend === 's3') {
            $expiresAt = CarbonImmutable::now()->addMinutes(5);
            $temporaryUrl = $this->storage->temporaryUrl($recording->local_path, $expiresAt, 's3');

            return redirect()->away($temporaryUrl);
        }

        $path = $recording->local_path;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $fullPath = Storage::disk('local')->path($path);

        return response()->file($fullPath, [
            'Content-Type' => $this->mimeType($recording->format),
            'Content-Disposition' => 'inline',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Recording::class);

        $filters = $this->validatedFilters($request);
        $piiMasking = $this->service->piiMaskingEnabled();

        $stream = $this->service->stream($filters);
        $filename = sprintf('recordings-%s.csv', CarbonImmutable::now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($stream, $piiMasking): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Recording ID',
                'Call Started At',
                'Agent',
                'Direction',
                'From Number',
                'To Number',
                'Duration (sec)',
                'Disposition',
                'Queue',
                'Storage Backend',
                'Recording Status',
                'Transcript Status',
                'Has QA Score',
            ]);

            $stream->each(function (Recording $recording) use ($handle, $piiMasking): void {
                $call = $recording->call;

                fputcsv($handle, [
                    $recording->id,
                    optional($call?->started_at)?->toDateTimeString(),
                    $call?->agent?->name ?? 'Unassigned',
                    $call?->direction ?? 'unknown',
                    format_phone($call?->from_number, $piiMasking),
                    format_phone($call?->to_number, $piiMasking),
                    $call?->duration_sec ?? 0,
                    $call?->disposition ?? 'unknown',
                    $call?->queue ?? 'default',
                    $recording->storage_backend,
                    $recording->status,
                    $recording->transcript?->status ?? 'missing',
                    ($call?->qaScores->isNotEmpty() ?? false) ? 'yes' : 'no',
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'agent' => ['nullable', 'integer', 'exists:users,id'],
            'direction' => ['nullable', 'in:inbound,outbound'],
            'number' => ['nullable', 'string', 'max:32'],
            'disposition' => ['nullable', 'string', 'max:191'],
            'queue' => ['nullable', 'string', 'max:191'],
            'has_transcript' => ['nullable', 'in:with,without'],
            'has_qa_score' => ['nullable', 'in:with,without'],
        ]);

        $from = isset($data['date_from']) ? CarbonImmutable::parse($data['date_from'])->startOfDay() : null;
        $to = isset($data['date_to']) ? CarbonImmutable::parse($data['date_to'])->endOfDay() : null;

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            [$data['date_from'], $data['date_to']] = [
                $to->startOfDay()->format('Y-m-d'),
                $from->endOfDay()->format('Y-m-d'),
            ];
        }

        return [
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'agent' => $data['agent'] ?? null,
            'direction' => $data['direction'] ?? null,
            'number' => $data['number'] ?? null,
            'disposition' => $data['disposition'] ?? null,
            'queue' => $data['queue'] ?? null,
            'has_transcript' => $data['has_transcript'] ?? null,
            'has_qa_score' => $data['has_qa_score'] ?? null,
        ];
    }

    private function mimeType(?string $format): string
    {
        return match (strtolower($format ?? '')) {
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/ogg',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            default => 'audio/mpeg',
        };
    }
}
