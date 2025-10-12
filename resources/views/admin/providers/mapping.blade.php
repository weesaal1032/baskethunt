<div class="max-w-5xl mx-auto space-y-10">
    <header class="space-y-2">
        <p class="text-sm uppercase tracking-wide text-brand-500 font-semibold">Providers</p>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Telephony API Mapping</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400">Map remote payloads into CallHub schemas, customise request metadata, and verify connectivity against the active provider credentials.</p>
    </header>

    <section class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
        <form method="POST" action="{{ route('admin.providers.telephony.mapping.update') }}" class="p-6 space-y-6">
            @csrf
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Request Configuration</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Control endpoint paths, custom headers, and baseline query parameters applied to telephony requests.</p>
            </div>
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label for="calls_endpoint" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Calls Endpoint</label>
                    <input id="calls_endpoint" name="calls_endpoint" type="text" value="{{ old('calls_endpoint', $form['calls_endpoint']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Relative path appended to the provider base URL (e.g. /calls).</p>
                </div>
                <div>
                    <label for="recording_endpoint" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Recording Endpoint</label>
                    <input id="recording_endpoint" name="recording_endpoint" type="text" value="{{ old('recording_endpoint', $form['recording_endpoint']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Must include <code class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-700">{callId}</code> placeholder (e.g. /calls/{callId}/recording).</p>
                </div>
            </div>
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label for="telephony_headers_json" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Custom Headers (JSON)</label>
                    <textarea id="telephony_headers_json" name="telephony_headers_json" rows="6" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder='{"Accept":"application/json"}'>{{ old('telephony_headers_json', $form['headers_json']) }}</textarea>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Merged with authentication header derived from the provider configuration.</p>
                </div>
                <div>
                    <label for="telephony_query_json" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Base Query Parameters (JSON)</label>
                    <textarea id="telephony_query_json" name="telephony_query_json" rows="6" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder='{"include":"recording"}'>{{ old('telephony_query_json', $form['query_json']) }}</textarea>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Appended to each request alongside the computed from/to window, pagination size, and cursor.</p>
                </div>
            </div>

            @php
                $mappingRows = collect(old('mapping', $form['mapping_rows'] ?? []))
                    ->map(fn ($row) => [
                        'key' => $row['key'] ?? '',
                        'path' => $row['path'] ?? '',
                    ])->values()->all();
                $knownKeys = $form['known_keys'] ?? [];
            @endphp
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Payload Field Mapping</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Define how remote payload keys translate into CallHub domain attributes. Dot-notation is supported for nested JSON fields.</p>
            </div>
            <div x-data="telephonyMapping({ rows: @json($mappingRows) })" class="space-y-4">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="grid gap-4 md:grid-cols-6 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Internal Field</label>
                            <input :name="`mapping[${index}][key]`" x-model="row.key" type="text" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="e.g. provider_call_id" required>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Provider JSON Path</label>
                            <input :name="`mapping[${index}][path]`" x-model="row.path" type="text" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="e.g. attributes.queueId">
                        </div>
                        <div class="md:col-span-1 flex justify-end">
                            <button type="button" class="mt-2 inline-flex items-center justify-center rounded-md border border-slate-300 dark:border-slate-600 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:border-rose-500 hover:text-rose-600" @click="removeRow(index)" x-show="rows.length > 1">
                                Remove
                            </button>
                        </div>
                    </div>
                </template>
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Common field keys: {{ implode(', ', $knownKeys) }}</p>
                    <button type="button" class="inline-flex items-center justify-center rounded-md border border-brand-500 px-3 py-2 text-xs font-semibold text-brand-600 hover:bg-brand-50 dark:hover:bg-slate-800" @click="addRow()">Add Field Mapping</button>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('admin.dashboard') }}" class="text-sm text-slate-600 dark:text-slate-400 hover:text-brand-500">Cancel</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-md bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">Save Mapping</button>
            </div>
        </form>
    </section>

    <section class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
        <form method="POST" action="{{ route('admin.providers.telephony.preview') }}" class="p-6 space-y-6">
            @csrf
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Connectivity Test</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Fetch a single page of calls using the configured window to validate credentials, rate limiting, and mapping behaviour.</p>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500">Run Preview</button>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                <div>
                    <label for="preview_from" class="block text-sm font-medium text-slate-700 dark:text-slate-300">From (optional)</label>
                    <input id="preview_from" name="from" type="date" value="{{ old('from') }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label for="preview_to" class="block text-sm font-medium text-slate-700 dark:text-slate-300">To (optional)</label>
                    <input id="preview_to" name="to" type="date" value="{{ old('to') }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label for="preview_cursor" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Cursor / Page Token</label>
                    <input id="preview_cursor" name="cursor" type="text" value="{{ old('cursor') }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="Optional pagination token">
                </div>
            </div>
            @if ($previewError)
                <div class="rounded border border-rose-500 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-700 dark:bg-rose-900/40 dark:text-rose-200">
                    {{ $previewError }}
                </div>
            @endif
            @if ($preview && !empty($preview['calls']))
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th scope="col" class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Provider Call ID</th>
                                <th scope="col" class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">From</th>
                                <th scope="col" class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">To</th>
                                <th scope="col" class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Status</th>
                                <th scope="col" class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Started</th>
                                <th scope="col" class="px-4 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Duration</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
                            @foreach ($preview['calls'] as $call)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $call['provider_call_id'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $call['from_number'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $call['to_number'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $call['status'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $call['started_at'] ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ $call['duration'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-sm text-slate-600 dark:text-slate-400">
                    @if (!empty($preview['next_cursor']))
                        <p>Next cursor available: <span class="font-mono">{{ $preview['next_cursor'] }}</span></p>
                    @else
                        <p>No additional pages advertised by provider.</p>
                    @endif
                </div>
            @elseif ($preview)
                <p class="text-sm text-slate-600 dark:text-slate-400">Provider request succeeded but no calls were returned for the requested window.</p>
            @endif
        </form>
    </section>
</div>
@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('telephonyMapping', ({ rows }) => ({
                rows: Array.isArray(rows) && rows.length ? rows : [{ key: 'provider_call_id', path: 'id' }],
                addRow() {
                    this.rows.push({ key: '', path: '' });
                },
                removeRow(index) {
                    if (this.rows.length > 1) {
                        this.rows.splice(index, 1);
                    }
                },
            }));
        });
    </script>
@endpush
