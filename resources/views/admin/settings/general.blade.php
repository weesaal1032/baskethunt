<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">General Settings</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Manage core metadata surfaced across emails, audit logs, and installer post-checks.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.general.update') }}" class="space-y-6">
        @csrf
        <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6">
                <div>
                    <label for="site_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Site Name</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Displayed in the navigation, emails, and health endpoints.</p>
                    <input
                        id="site_name"
                        name="site_name"
                        type="text"
                        class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500"
                        value="{{ old('site_name', $form['site_name']) }}"
                        required
                    >
                </div>

                <div>
                    <label for="timezone" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Timezone</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Used for scheduling jobs, retention windows, and dashboard date displays.</p>
                    <select
                        id="timezone"
                        name="timezone"
                        class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500"
                        required
                    >
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $form['timezone']) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="app_url" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Application URL</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Base URL for generating links in notifications, signed URLs, and health pings.</p>
                    <input
                        id="app_url"
                        name="app_url"
                        type="url"
                        class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500"
                        value="{{ old('app_url', $form['app_url']) }}"
                        required
                    >
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-right">
                <button type="submit" class="inline-flex items-center justify-center rounded-md bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">Save Changes</button>
            </div>
        </div>
    </form>
</div>
