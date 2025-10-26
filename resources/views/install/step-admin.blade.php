<div class="space-y-8">
    @include('install.partials.progress', ['progress' => $progress])

    <div class="rounded-xl border border-slate-200 bg-white/80 p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold">Administrator account</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Create or confirm the primary administrator credentials. This account will have full access to manage CallHub and
                configure future integrations.
            </p>
        </header>

        <form method="POST" action="{{ route('install.store') }}" class="mt-6 space-y-6">
            @csrf
            <input type="hidden" name="step" value="admin" />

            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-medium" for="admin_name">Full name</label>
                    <input id="admin_name" name="admin_name" type="text" value="{{ old('admin_name', $defaults['admin_name'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-medium" for="admin_email">Email address</label>
                    <input id="admin_email" name="admin_email" type="email" value="{{ old('admin_email', $defaults['admin_email'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="admin_password">Password</label>
                    <input id="admin_password" name="admin_password" type="password" required minlength="12" autocomplete="new-password" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    <p class="text-xs text-slate-500 dark:text-slate-400">Minimum 12 characters with mixed complexity recommended.</p>
                </div>
                <div class="space-y-2">
                    <label class="block text-sm font-medium" for="admin_password_confirmation">Confirm password</label>
                    <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required minlength="12" autocomplete="new-password" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-slate-200 pt-6 text-sm dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    The installer ensures the default provider stub exists for immediate API integration testing.
                </p>
                <button type="submit" class="rounded bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500/90">
                    Save administrator
                </button>
            </div>
        </form>
    </div>
</div>
