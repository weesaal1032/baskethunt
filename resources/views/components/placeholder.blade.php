<section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
        <h1 class="text-xl font-semibold">{{ $heading ?? 'Coming Soon' }}</h1>
    </div>
    <div class="space-y-4 px-6 py-6 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
        <p>{{ $body ?? 'Implementation pending.' }}</p>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Roadmap</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Repository layer wiring</li>
                    <li>Queue-backed processing</li>
                    <li>Observability dashboards</li>
                </ul>
            </div>
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Next Steps</h2>
                <p>Connect providers, configure transcription backends, and set QA rubrics.</p>
            </div>
        </div>
    </div>
</section>
