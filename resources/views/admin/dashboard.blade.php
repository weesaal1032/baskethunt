@php
    $formatNumber = fn (int $value) => number_format($value);
    $formatBytes = function (?int $bytes): string {
        if ($bytes === null) {
            return 'N/A';
        }

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return sprintf('%.1f %s', $value, $units[$power]);
    };
@endphp

<div class="space-y-10">
    <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Operations Overview</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Updated {{ $metrics->generatedAt->toDayDateTimeString() }}
            </p>
            @if ($metrics->lastPollAt)
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Last successful call poll: {{ $metrics->lastPollAt->diffForHumans() }}
                </p>
            @else
                <p class="text-sm text-amber-600 dark:text-amber-400">No successful call poll recorded yet.</p>
            @endif
        </div>
        @if (auth()->user()?->role === 'admin')
            <a href="{{ route('admin.logs') }}" class="inline-flex items-center gap-2 rounded border border-slate-300 px-3 py-2 text-sm font-medium hover:border-brand-500 hover:text-brand-500 dark:border-slate-600">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                View application logs
            </a>
        @endif
    </div>

    @if (count($metrics->alerts) > 0)
        <div class="space-y-2 rounded border border-amber-500 bg-amber-50 px-4 py-3 text-amber-900 dark:border-amber-600 dark:bg-amber-950/60 dark:text-amber-200">
            <p class="font-semibold">Alerts</p>
            <ul class="list-disc space-y-1 pl-5 text-sm">
                @foreach ($metrics->alerts as $alert)
                    <li>{{ $alert }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <p class="text-sm text-slate-500">Calls ingested (24h)</p>
            <p class="mt-2 text-3xl font-semibold">{{ $formatNumber($metrics->callsIngested24h) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <p class="text-sm text-slate-500">Recordings ready (24h)</p>
            <p class="mt-2 text-3xl font-semibold">{{ $formatNumber($metrics->recordingsReady24h) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <p class="text-sm text-slate-500">Transcripts completed (24h)</p>
            <p class="mt-2 text-3xl font-semibold">{{ $formatNumber($metrics->transcriptsReady24h) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <p class="text-sm text-slate-500">Domain queue depth</p>
            <p class="mt-2 text-3xl font-semibold">{{ $formatNumber($metrics->queueDepth) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $formatNumber($metrics->queueBacklog) }} jobs pending in Laravel queue</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <p class="text-sm text-slate-500">Job failures (24h)</p>
            <p class="mt-2 text-3xl font-semibold text-rose-600 dark:text-rose-400">{{ $formatNumber($metrics->domainFailedJobs24h + $metrics->queueFailedJobs) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $formatNumber($metrics->domainFailedJobs24h) }} domain / {{ $formatNumber($metrics->queueFailedJobs) }} queue</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <p class="text-sm text-slate-500">Local disk usage</p>
            <p class="mt-2 text-3xl font-semibold">
                @if ($metrics->diskUsage->usedPercent === null)
                    Unknown
                @else
                    {{ number_format($metrics->diskUsage->usedPercent, 1) }}%
                @endif
            </p>
            <p class="mt-2 text-xs text-slate-500">
                {{ $formatBytes($metrics->diskUsage->usedBytes) }} used of {{ $formatBytes($metrics->diskUsage->totalBytes) }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <h2 class="text-lg font-semibold">Storage breakdown</h2>
            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-2">Backend</th>
                        <th class="py-2">Recordings</th>
                        <th class="py-2">Size</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($metrics->storageBreakdown as $breakdown)
                        <tr>
                            <td class="py-2 capitalize">{{ str_replace('_', ' ', $breakdown->backend) }}</td>
                            <td class="py-2">{{ $formatNumber($breakdown->recordings) }}</td>
                            <td class="py-2">{{ $formatBytes($breakdown->bytes) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
            <h2 class="text-lg font-semibold">Operational guidance</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
                <li>Monitor queue depth — sustained growth suggests scaling workers or investigating slow jobs.</li>
                <li>Review alerts and rotate storage if disk utilisation approaches the configured threshold.</li>
                <li>Use the application logs link to inspect recent failures and confirm recovery actions.</li>
            </ul>
        </div>
    </div>
</div>
