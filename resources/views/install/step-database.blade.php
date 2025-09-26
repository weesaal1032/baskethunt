<div class="space-y-8">
    @include('install.partials.progress', ['progress' => $progress])

    <div class="rounded-xl border border-slate-200 bg-white/80 p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold">Database configuration</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Provide connection details for the MySQL or MariaDB database CallHub will use. The installer will verify the
                connection and run all migrations and seeders.
            </p>
        </header>

        <form method="POST" action="{{ route('install.store') }}" class="mt-6 space-y-6">
            @csrf
            <input type="hidden" name="step" value="database" />

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="db_host">Database host</label>
                    <input id="db_host" name="db_host" type="text" value="{{ old('db_host', $defaults['db_host'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="db_port">Port</label>
                    <input id="db_port" name="db_port" type="text" value="{{ old('db_port', $defaults['db_port'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="db_database">Database name</label>
                    <input id="db_database" name="db_database" type="text" value="{{ old('db_database', $defaults['db_database'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="db_username">Username</label>
                    <input id="db_username" name="db_username" type="text" value="{{ old('db_username', $defaults['db_username'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-medium" for="db_password">Password</label>
                    <input id="db_password" name="db_password" type="password" value="{{ old('db_password', $defaults['db_password'] ?? '') }}" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" autocomplete="new-password" />
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-200 pt-6 text-sm dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Ensure the database user has permission to create tables, indexes, and JSON columns.
                </p>
                <button type="submit" class="rounded bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500/90">
                    Test connection & migrate
                </button>
            </div>
        </form>
    </div>
</div>
