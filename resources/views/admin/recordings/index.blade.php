@php
    $formatDuration = function (?int $seconds): string {
        if ($seconds === null) {
            return '—';
        }

        $seconds = max($seconds, 0);
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    };
@endphp

<div class="space-y-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Recording Library</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Filter and export stored call recordings with transcript and QA coverage.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a
                href="{{ route('admin.recordings.export', array_filter($filters, fn ($value) => $value !== null && $value !== '')) }}"
                class="inline-flex items-center gap-2 rounded border border-slate-300 bg-white px-3 py-2 text-sm font-medium shadow-sm hover:border-brand-500 hover:text-brand-500 dark:border-slate-600 dark:bg-slate-800"
            >
                Export CSV
            </a>
        </div>
    </div>

    <form method="GET" class="grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800 md:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="date_from" class="block text-sm font-medium text-slate-700 dark:text-slate-300">From date</label>
            <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="date_to" class="block text-sm font-medium text-slate-700 dark:text-slate-300">To date</label>
            <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="agent" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Agent</label>
            <select id="agent" name="agent" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                <option value="">Any</option>
                @foreach ($agents as $agent)
                    <option value="{{ $agent->id }}" @selected((string) $agent->id === (string) $filters['agent'])>{{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="direction" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Direction</label>
            <select id="direction" name="direction" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                <option value="">Any</option>
                <option value="inbound" @selected($filters['direction'] === 'inbound')>Inbound</option>
                <option value="outbound" @selected($filters['direction'] === 'outbound')>Outbound</option>
            </select>
        </div>
        <div>
            <label for="number" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Number contains</label>
            <input id="number" name="number" type="text" value="{{ $filters['number'] }}" placeholder="Search" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="disposition" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Disposition</label>
            <input id="disposition" name="disposition" type="text" value="{{ $filters['disposition'] }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="queue" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Queue</label>
            <input id="queue" name="queue" type="text" value="{{ $filters['queue'] }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label for="has_transcript" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Transcript</label>
            <select id="has_transcript" name="has_transcript" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                <option value="">Any</option>
                <option value="with" @selected($filters['has_transcript'] === 'with')>Has transcript</option>
                <option value="without" @selected($filters['has_transcript'] === 'without')>Missing transcript</option>
            </select>
        </div>
        <div>
            <label for="has_qa_score" class="block text-sm font-medium text-slate-700 dark:text-slate-300">QA Score</label>
            <select id="has_qa_score" name="has_qa_score" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                <option value="">Any</option>
                <option value="with" @selected($filters['has_qa_score'] === 'with')>Has QA score</option>
                <option value="without" @selected($filters['has_qa_score'] === 'without')>Missing QA score</option>
            </select>
        </div>
        <div>
            <label for="per_page" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Results per page</label>
            <select id="per_page" name="per_page" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                @foreach ([25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2 lg:col-span-3 flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.recordings.index') }}" class="inline-flex items-center gap-2 rounded border border-slate-300 px-3 py-2 text-sm font-medium hover:border-rose-500 hover:text-rose-500 dark:border-slate-600">Reset</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-600">Apply filters</button>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/70">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-4 py-3">Started</th>
                    <th class="px-4 py-3">Agent</th>
                    <th class="px-4 py-3">From → To</th>
                    <th class="px-4 py-3">Direction</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Disposition</th>
                    <th class="px-4 py-3">Storage</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Transcript</th>
                    <th class="px-4 py-3">QA</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-700 dark:bg-slate-900/60">
                @forelse ($recordings as $recording)
                    @php
                        $call = $recording->call;
                        $transcriptReady = $recording->transcript?->status === 'ready';
                        $hasQa = $call?->qaScores->isNotEmpty() ?? false;
                    @endphp
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/70">
                        <td class="px-4 py-3 whitespace-nowrap">{{ optional($call?->started_at)?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $call?->agent?->name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ format_phone($call?->from_number, $piiMasking) }} → {{ format_phone($call?->to_number, $piiMasking) }}</td>
                        <td class="px-4 py-3 capitalize">{{ $call?->direction ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $formatDuration($call?->duration_sec) }}</td>
                        <td class="px-4 py-3">{{ $call?->disposition ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $recording->storage_backend) }}</td>
                        <td class="px-4 py-3 capitalize">{{ $recording->status }}</td>
                        <td class="px-4 py-3">
                            @if ($transcriptReady)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">Ready</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Missing</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($hasQa)
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-100 px-2 py-1 text-xs font-medium text-brand-600 dark:bg-brand-500/20 dark:text-brand-200">Scored</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-200 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Pending</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">Coming soon</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-6 text-center text-sm text-slate-500 dark:text-slate-400">No recordings matched the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $recordings->withQueryString()->links() }}
    </div>
</div>
