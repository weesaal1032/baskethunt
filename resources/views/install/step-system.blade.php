<div class="space-y-8">
    @include('install.partials.progress', ['progress' => $progress])

    <div class="rounded-xl border border-slate-200 bg-white/80 p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold">System checks</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Verify the hosting environment meets CallHub's minimum requirements before continuing.
            </p>
        </header>

        <ul class="mt-6 space-y-4">
            @foreach ($checks as $check)
                <li class="flex items-start justify-between gap-6 rounded-lg border border-slate-100 bg-white/90 px-4 py-3 text-sm dark:border-slate-700/70 dark:bg-slate-900/50">
                    <div>
                        <p class="font-semibold">{{ $check['label'] }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $check['details'] }}</p>
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-emerald-500/15 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200' => $check['passed'],
                        'bg-rose-500/15 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200' => ! $check['passed'],
                    ])>
                        {{ $check['passed'] ? 'Pass' : 'Fail' }}
                    </span>
                </li>
            @endforeach
        </ul>

        <form method="POST" action="{{ route('install.store') }}" class="mt-8 flex items-center justify-between">
            @csrf
            <input type="hidden" name="step" value="system" />

            <p class="text-xs text-slate-500 dark:text-slate-400">
                All checks must pass before proceeding. Update PHP extensions or permissions as needed.
            </p>

            <button type="submit" @class([
                'rounded bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500/90',
                'cursor-not-allowed opacity-50' => ! $allPassed,
            ]) {{ $allPassed ? '' : 'disabled' }}>
                Continue to database setup
            </button>
        </form>
    </div>
</div>
