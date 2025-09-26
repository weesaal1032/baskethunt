<div class="space-y-8">
    @include('install.partials.progress', ['progress' => $progress])

    <div class="rounded-xl border border-slate-200 bg-white/80 p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
        <header class="space-y-2">
            <h1 class="text-2xl font-semibold">Application configuration</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Finalize the runtime configuration written to <code>.env</code>. These values drive URLs, queues, mail delivery, storage
                backends, and timezone defaults.
            </p>
        </header>

        <form method="POST" action="{{ route('install.store') }}" class="mt-6 space-y-6">
            @csrf
            <input type="hidden" name="step" value="app" />

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">App basics</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium" for="app_name">Application name</label>
                        <input id="app_name" name="app_name" type="text" value="{{ old('app_name', $defaults['app_name'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium" for="app_url">Base URL</label>
                        <input id="app_url" name="app_url" type="url" value="{{ old('app_url', $defaults['app_url'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium" for="app_key">Application key</label>
                        <input id="app_key" name="app_key" type="text" value="{{ old('app_key', $defaults['app_key'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        <p class="text-xs text-slate-500 dark:text-slate-400">Use the generated base64 value or run <code>php artisan key:generate --show</code>.</p>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium" for="timezone">Timezone</label>
                        <input id="timezone" name="timezone" type="text" value="{{ old('timezone', $defaults['timezone'] ?? 'UTC') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" placeholder="UTC" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">Mail</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="mail_mailer">Mailer</label>
                        <input id="mail_mailer" name="mail_mailer" type="text" value="{{ old('mail_mailer', $defaults['mail_mailer'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="mail_host">Host</label>
                        <input id="mail_host" name="mail_host" type="text" value="{{ old('mail_host', $defaults['mail_host'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="mail_port">Port</label>
                        <input id="mail_port" name="mail_port" type="text" value="{{ old('mail_port', $defaults['mail_port'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="mail_encryption">Encryption</label>
                        <input id="mail_encryption" name="mail_encryption" type="text" value="{{ old('mail_encryption', $defaults['mail_encryption'] ?? '') }}" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" placeholder="tls" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="mail_username">Username</label>
                        <input id="mail_username" name="mail_username" type="text" value="{{ old('mail_username', $defaults['mail_username'] ?? '') }}" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="mail_password">Password</label>
                        <input id="mail_password" name="mail_password" type="password" value="{{ old('mail_password', $defaults['mail_password'] ?? '') }}" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" autocomplete="new-password" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium" for="mail_from_address">From address</label>
                        <input id="mail_from_address" name="mail_from_address" type="email" value="{{ old('mail_from_address', $defaults['mail_from_address'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <label class="block text-sm font-medium" for="mail_from_name">From name</label>
                        <input id="mail_from_name" name="mail_from_name" type="text" value="{{ old('mail_from_name', $defaults['mail_from_name'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-semibold">Queues & storage</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="default_storage">Default storage backend</label>
                        <select id="default_storage" name="default_storage" class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                            <option value="local" @selected(old('default_storage', $defaults['default_storage'] ?? 'local') === 'local')>Local (default)</option>
                            <option value="s3" @selected(old('default_storage', $defaults['default_storage'] ?? 'local') === 's3')>S3-compatible (S3/R2/MinIO)</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-medium" for="queue_connection">Queue connection</label>
                        <input id="queue_connection" name="queue_connection" type="text" value="{{ old('queue_connection', $defaults['queue_connection'] ?? '') }}" required class="w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900" />
                        <p class="text-xs text-slate-500 dark:text-slate-400">Use <code>database</code> for cPanel-friendly queue workers.</p>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-between border-t border-slate-200 pt-6 text-sm dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    The installer writes <code>.env</code>, locks the wizard, and surfaces cPanel Cron instructions on success.
                </p>
                <button type="submit" class="rounded bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-500/90">
                    Write configuration
                </button>
            </div>
        </form>
    </div>
</div>
