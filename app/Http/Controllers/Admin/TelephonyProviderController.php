<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Providers\Exceptions\TelephonyClientException;
use App\Services\Providers\TelephonyClientInterface;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class TelephonyProviderController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly TelephonyClientInterface $telephonyClient,
    ) {
    }

    public function mapping(Request $request): View
    {
        $form = $this->formState();

        return view('admin.providers.mapping', [
            'form' => $form,
            'preview' => $request->session()->get('telephony_preview'),
            'previewError' => $request->session()->get('telephony_preview_error'),
        ]);
    }

    public function updateMapping(Request $request): RedirectResponse
    {
        $validated = $this->validateMapping($request);

        $headers = $this->decodeJson($validated['telephony_headers_json'] ?? null);
        $query = $this->decodeJson($validated['telephony_query_json'] ?? null);

        $mapping = [
            'provider_call_id' => $this->nullableString($validated['mapping_provider_call_id'] ?? null),
            'from_number' => $this->nullableString($validated['mapping_from_number'] ?? null),
            'to_number' => $this->nullableString($validated['mapping_to_number'] ?? null),
            'started_at' => $this->nullableString($validated['mapping_started_at'] ?? null),
            'ended_at' => $this->nullableString($validated['mapping_ended_at'] ?? null),
            'duration' => $this->nullableString($validated['mapping_duration'] ?? null),
            'status' => $this->nullableString($validated['mapping_status'] ?? null),
            'recording_url' => $this->nullableString($validated['mapping_recording_url'] ?? null),
        ];

        $this->settings->setMany([
            'telephony.provider.mapping' => $mapping,
            'telephony.provider.request_headers' => $headers,
            'telephony.provider.query_defaults' => $query,
            'telephony.provider.calls_endpoint' => $validated['calls_endpoint'],
            'telephony.provider.recording_endpoint' => $validated['recording_endpoint'],
        ]);

        return redirect()
            ->route('admin.providers.telephony.mapping')
            ->with('telephony_preview', null)
            ->with('telephony_preview_error', null)
            ->with('status', 'Telephony provider mapping saved.');
    }

    public function preview(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'cursor' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.providers.telephony.mapping')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $pollWindowDays = (int) $this->settings->get('telephony.provider.poll_window_days', 7);
        $now = CarbonImmutable::now();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $now->subDays(max($pollWindowDays, 1));
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : $now;

        try {
            $page = $this->telephonyClient->listCalls($from, $to, $data['cursor'] ?? null);
        } catch (TelephonyClientException $exception) {
            return redirect()
                ->route('admin.providers.telephony.mapping')
                ->with('telephony_preview_error', $exception->getMessage())
                ->withErrors(['telephony' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.providers.telephony.mapping')
                ->with('telephony_preview_error', 'Unexpected error communicating with provider.');
        }

        return redirect()
            ->route('admin.providers.telephony.mapping')
            ->with('telephony_preview', [
                'calls' => $page->calls()->map(fn ($call) => $call)->all(),
                'next_cursor' => $page->nextCursor(),
            ])
            ->with('telephony_preview_error', null)
            ->with('status', 'Telephony API responded successfully.');
    }

    private function formState(): array
    {
        $mapping = $this->settings->get('telephony.provider.mapping', []);
        if (! is_array($mapping)) {
            $mapping = [];
        }

        $headers = $this->settings->get('telephony.provider.request_headers', []);
        if (! is_array($headers)) {
            $headers = [];
        }

        $query = $this->settings->get('telephony.provider.query_defaults', []);
        if (! is_array($query)) {
            $query = [];
        }

        $defaultMapping = [
            'provider_call_id' => 'id',
            'from_number' => 'from',
            'to_number' => 'to',
            'started_at' => 'started_at',
            'ended_at' => 'ended_at',
            'duration' => 'duration',
            'status' => 'status',
            'recording_url' => 'recording_url',
        ];

        $sanitizedMapping = [];
        foreach ($mapping as $key => $value) {
            if (is_string($value) && $value !== '') {
                $sanitizedMapping[$key] = $value;
            }
        }

        return [
            'mapping' => array_merge($defaultMapping, $sanitizedMapping),
            'headers_json' => $headers !== [] ? json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '',
            'query_json' => $query !== [] ? json_encode($query, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '',
            'calls_endpoint' => (string) $this->settings->get('telephony.provider.calls_endpoint', '/calls'),
            'recording_endpoint' => (string) $this->settings->get('telephony.provider.recording_endpoint', '/calls/{callId}/recording'),
        ];
    }

    private function validateMapping(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'mapping_provider_call_id' => ['required', 'string', 'max:255'],
            'mapping_from_number' => ['nullable', 'string', 'max:255'],
            'mapping_to_number' => ['nullable', 'string', 'max:255'],
            'mapping_started_at' => ['nullable', 'string', 'max:255'],
            'mapping_ended_at' => ['nullable', 'string', 'max:255'],
            'mapping_duration' => ['nullable', 'string', 'max:255'],
            'mapping_status' => ['nullable', 'string', 'max:255'],
            'mapping_recording_url' => ['nullable', 'string', 'max:255'],
            'telephony_headers_json' => ['nullable', 'json'],
            'telephony_query_json' => ['nullable', 'json'],
            'calls_endpoint' => ['required', 'string', 'max:255'],
            'recording_endpoint' => ['required', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $recordingEndpoint = $request->input('recording_endpoint');
            if (is_string($recordingEndpoint) && ! Str::contains($recordingEndpoint, '{callId}')) {
                $validator->errors()->add('recording_endpoint', 'Recording endpoint must include a {callId} placeholder.');
            }
        });

        return $validator->validate();
    }

    private function decodeJson(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function nullableString(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
