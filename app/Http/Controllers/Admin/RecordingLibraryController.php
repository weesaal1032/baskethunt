<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use App\Services\Audit\AuditLogger;
use App\Services\Qa\QaScoringService;
use App\Services\Recordings\RecordingLibraryService;
use App\Services\Storage\StorageService;
use App\Support\Filters\CallFilters;
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
        private readonly StorageService $storage,
        private readonly QaScoringService $qa,
        private readonly AuditLogger $audit,
        private readonly CallFilters $filters
    )
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Recording::class);

        $filters = $this->filters->normalise($request->all());
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

    public function show(Request $request, Recording $recording): View
    {
        $this->authorize('view', $recording);

        $detail = $this->service->detail($recording->id);

        abort_if($detail === null, 404);

        $piiMasking = $this->service->piiMaskingEnabled();
        $user = $request->user();
        abort_if($user === null, 403);
        $qaContext = $this->qa->workspace($detail->call, $user);
        $this->audit->log($user, 'recording.view', $recording, [
            'call_id' => $recording->call_id,
        ]);
        $audioUrl = URL::temporarySignedRoute(
            'admin.recordings.audio',
            CarbonImmutable::now()->addMinutes(2),
            ['recording' => $recording->id]
        );

        return view('admin.recordings.show', [
            'recording' => $detail,
            'call' => $detail->call,
            'transcript' => $detail->transcript,
            'piiMasking' => $piiMasking,
            'audioUrl' => $audioUrl,
            'qaContext' => $qaContext,
        ]);
    }

    public function audio(Request $request, Recording $recording): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('view', $recording);

        if ($recording->status !== 'ready' || $recording->local_path === null) {
            abort(404);
        }

        $user = $request->user();
        abort_if($user === null, 403);

        if ($recording->storage_backend === 's3') {
            $expiresAt = CarbonImmutable::now()->addMinute();
            $temporaryUrl = $this->storage->temporaryUrl($recording->local_path, $expiresAt, 's3');

            $this->audit->log($user, 'recording.audio.stream', $recording, [
                'storage_backend' => 's3',
            ]);

            return redirect()->away($temporaryUrl);
        }

        $path = $recording->local_path;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $fullPath = Storage::disk('local')->path($path);

        $this->audit->log($user, 'recording.audio.stream', $recording, [
            'storage_backend' => 'local',
        ]);

        return response()->file($fullPath, [
            'Content-Type' => $this->mimeType($recording->format),
            'Content-Disposition' => 'inline',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Recording::class);

        $filters = $this->filters->normalise($request->all());
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
