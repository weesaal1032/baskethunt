<div class="space-y-8">
    @include('install.partials.progress', ['progress' => $progress])

    <div class="rounded-xl border border-emerald-400 bg-emerald-50/80 p-8 shadow-sm dark:border-emerald-500/60 dark:bg-emerald-900/20">
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold text-emerald-800 dark:text-emerald-200">Installation complete</h1>
            <p class="text-sm text-emerald-700 dark:text-emerald-100">
                CallHub is configured and the installer is now locked. Keep the generated <code>.env</code> safe and proceed to sign in.
            </p>
            <p class="text-xs text-emerald-600 dark:text-emerald-200">
                Base URL: <a href="{{ $appUrl }}" class="underline decoration-emerald-500 hover:text-emerald-800 dark:hover:text-emerald-100">{{ $appUrl }}</a>
            </p>
        </header>

        <div class="mt-6 space-y-4">
            <h2 class="text-lg font-semibold text-emerald-800 dark:text-emerald-100">cPanel Cron reminders</h2>
            <p class="text-sm text-emerald-700 dark:text-emerald-100">
                Add the following Cron entries through your hosting panel, replacing <code>/home/{cpanel_user}/callhub</code> with your actual
                deployment path. Both commands should run every minute.
            </p>
            <ul class="space-y-4 text-sm">
                @foreach ($cron as $entry)
                    <li class="rounded-lg border border-emerald-200 bg-white/70 p-4 dark:border-emerald-500/40 dark:bg-emerald-900/30">
                        <p class="text-base font-semibold text-emerald-800 dark:text-emerald-100">{{ $entry['title'] }}</p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-200">{{ $entry['description'] }}</p>
                        <code class="mt-2 block whitespace-pre-wrap rounded bg-slate-900/90 px-3 py-2 text-xs text-emerald-100">
                            {{ $entry['example'] }}
                        </code>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-6 flex flex-col items-start gap-3 text-sm">
            <a href="{{ route('auth.login') }}" class="inline-flex items-center gap-2 rounded bg-brand-500 px-4 py-2 font-semibold text-white shadow-sm transition hover:bg-brand-500/90">
                Go to sign in
            </a>
            <p class="text-xs text-emerald-700 dark:text-emerald-100">
                If you need to re-run the installer, delete <code>storage/installed.flag</code> manually after backing up your database and .env.
            </p>
        </div>
    </div>
</div>
