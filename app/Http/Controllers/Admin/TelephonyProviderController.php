<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Providers\Exceptions\TelephonyClientException;
use App\Services\Providers\TelephonyClientInterface;
use App\Services\Settings\SettingsService;
use App\Support\Providers\TelephonyMappingDefaults;
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
        $mapping = $this->normaliseMappingRows($validated['mapping']);

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
        $storedMapping = $this->settings->get('telephony.provider.mapping', []);
        if (! is_array($storedMapping)) {
            $storedMapping = [];
        }

        $headers = $this->settings->get('telephony.provider.request_headers', []);
        if (! is_array($headers)) {
            $headers = [];
        }

        $query = $this->settings->get('telephony.provider.query_defaults', []);
        if (! is_array($query)) {
            $query = [];
        }

        return [
            'mapping_rows' => $this->mappingRows($storedMapping),
            'known_keys' => array_keys(TelephonyMappingDefaults::values()),
            'headers_json' => $headers !== [] ? json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '',
            'query_json' => $query !== [] ? json_encode($query, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '',
            'calls_endpoint' => (string) $this->settings->get('telephony.provider.calls_endpoint', '/calls'),
            'recording_endpoint' => (string) $this->settings->get('telephony.provider.recording_endpoint', '/calls/{callId}/recording'),
        ];
    }

    private function validateMapping(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*.key' => ['required', 'string', 'max:64'],
            'mapping.*.path' => ['nullable', 'string', 'max:255'],
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

            $rows = $request->input('mapping', []);
            $providerKeyPresent = false;

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $key = Str::of((string) ($row['key'] ?? ''))->snake()->value();
                    $path = $this->nullableString($row['path'] ?? null);

                    if ($key === 'provider_call_id' && $path !== null) {
                        $providerKeyPresent = true;
                        break;
                    }
                }
            }

            if (! $providerKeyPresent) {
                $validator->errors()->add('mapping', 'A provider_call_id mapping with a value is required.');
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

    /**
     * @param array<int, array{key?: string, path?: string|null}> $rows
     * @return array<string, string|null>
     */
    private function normaliseMappingRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = Str::of((string) ($row['key'] ?? ''))->snake()->value();

            if ($key === '') {
                continue;
            }

            $normalized[$key] = $this->nullableString($row['path'] ?? null);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $storedMapping
     * @return array<int, array{key: string, path: string}>
     */
    private function mappingRows(array $storedMapping): array
    {
        $rows = [];
        $defaults = TelephonyMappingDefaults::values();
        $seen = [];

        foreach ($storedMapping as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'path' => is_string($value) ? $value : '',
            ];
            $seen[$key] = true;
        }

        foreach ($defaults as $key => $value) {
            if (isset($seen[$key])) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'path' => is_string($value) ? $value : '',
            ];
        }

        if ($rows === []) {
            $rows[] = ['key' => 'provider_call_id', 'path' => 'id'];
        }

        return $rows;
    }
}
