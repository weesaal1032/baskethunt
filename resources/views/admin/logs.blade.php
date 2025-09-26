<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Application Logs</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Latest {{ count($lines) }} lines from storage/logs/laravel.log.</p>
    </div>
    @if (empty($lines))
        <p class="rounded border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200">
            Log file is currently empty or missing.
        </p>
    @else
        <pre class="max-h-[600px] overflow-auto rounded border border-slate-200 bg-slate-900 p-4 text-xs text-slate-100 dark:border-slate-700">
@foreach ($lines as $line){{ $line }}
@endforeach        </pre>
    @endif
</div>
