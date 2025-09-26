<div class="rounded-lg border border-slate-200 bg-white/70 p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800/60">
    <ol class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        @foreach ($progress as $index => $step)
            <li class="flex items-center gap-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold @class([
                        'bg-emerald-500 text-white' => $step['status'] === 'complete',
                        'bg-brand-500 text-white' => $step['status'] === 'current',
                        'border border-slate-300 bg-white text-slate-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400' => $step['status'] === 'pending',
                    ])"
                >
                    @if ($step['status'] === 'complete')
                        ✓
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <div class="text-sm">
                    <p class="font-semibold">{{ $step['label'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 capitalize">{{ $step['status'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</div>
