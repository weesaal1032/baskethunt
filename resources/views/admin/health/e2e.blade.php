@php
    /** @var \App\Services\Health\E2eHealthCheckReport $report */
@endphp

<div class="space-y-8">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold">End-to-End Validation</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            This diagnostic run provisions sample data, exercises background jobs, and verifies exports/RBAC to confirm the stack is configured correctly.
        </p>
        @if ($report->successful)
            <div class="inline-flex items-center gap-2 rounded border border-emerald-400 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 dark:border-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-200">
                <span class="text-lg">✅</span>
                All checks completed successfully.
            </div>
        @else
            <div class="inline-flex items-center gap-2 rounded border border-rose-400 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 dark:border-rose-600 dark:bg-rose-900/40 dark:text-rose-200">
                <span class="text-lg">⚠️</span>
                Some checks failed. Review the details below to address configuration gaps.
            </div>
        @endif
    </header>

    <section class="space-y-4">
        @foreach ($report->steps as $step)
            <article class="rounded-lg border border-slate-200 bg-white/70 p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-lg font-semibold">
                            <span class="text-xl">{{ $step->passed ? '✅' : '❌' }}</span>
                            <span>{{ $step->label }}</span>
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $step->message }}</p>
                    </div>
                    <span class="text-xs uppercase tracking-wide text-slate-400">{{ $step->key }}</span>
                </div>
                @if ($step->details !== [])
                    <details class="mt-3 rounded bg-slate-100 p-3 text-sm dark:bg-slate-900/40">
                        <summary class="cursor-pointer font-medium text-slate-600 dark:text-slate-300">Details</summary>
                        <pre class="mt-2 overflow-x-auto whitespace-pre-wrap text-xs text-slate-600 dark:text-slate-200">{{ json_encode($step->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                @endif
            </article>
        @endforeach
    </section>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Dashboard Snapshot</h2>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded border border-slate-200 bg-white/70 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-slate-500 dark:text-slate-300">Calls (24h)</p>
                <p class="mt-1 text-2xl font-semibold">{{ $report->metrics['calls_24h'] ?? '—' }}</p>
            </div>
            <div class="rounded border border-slate-200 bg-white/70 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-slate-500 dark:text-slate-300">Recordings Ready (24h)</p>
                <p class="mt-1 text-2xl font-semibold">{{ $report->metrics['recordings_24h'] ?? '—' }}</p>
            </div>
            <div class="rounded border border-slate-200 bg-white/70 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-slate-500 dark:text-slate-300">Transcripts Ready (24h)</p>
                <p class="mt-1 text-2xl font-semibold">{{ $report->metrics['transcripts_24h'] ?? '—' }}</p>
            </div>
            <div class="rounded border border-slate-200 bg-white/70 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/70">
                <p class="text-slate-500 dark:text-slate-300">Queue Depth</p>
                <p class="mt-1 text-2xl font-semibold">{{ $report->metrics['queue_depth'] ?? '—' }}</p>
            </div>
        </div>
        @if (!empty($report->metrics['alerts']))
            <div class="rounded border border-amber-400 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500 dark:bg-amber-900/30 dark:text-amber-200">
                <p class="font-medium">Active Alerts</p>
                <ul class="mt-2 list-disc space-y-1 pl-4">
                    @foreach ($report->metrics['alerts'] as $alert)
                        <li>{{ $alert }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>
