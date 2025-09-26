<style>[x-cloak]{display:none!important;}</style>

@php
    $qaInitialRubric = $form['qa']['rubric'];

    if (old('qa_rubric')) {
        $decodedRubric = json_decode(old('qa_rubric'), true);

        if (is_array($decodedRubric)) {
            $qaInitialRubric = $decodedRubric;
        }
    }
@endphp

<div class="max-w-5xl mx-auto" x-data="{
        tab: 'general',
        storageDriver: @js(old('storage_driver', $form['storage']['driver'])),
        transcriptionEngine: @js(old('transcription_engine', $form['transcription']['engine'])),
    }">
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Administration Settings</h1>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Configure CallHub platform defaults, provider integrations, and operational safeguards.</p>
    </div>

    <div class="border-b border-slate-200 dark:border-slate-700">
        <nav class="flex flex-wrap gap-2 text-sm">
            @php
                $tabs = [
                    'general' => 'General',
                    'telephony' => 'Telephony Provider',
                    'storage' => 'Storage',
                    'transcription' => 'Transcription',
                    'notifications' => 'Notifications',
                    'qa' => 'QA Rubric',
                    'privacy' => 'Privacy & Retention',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button type="button" @click="tab='{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-brand-500 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'" class="rounded-md px-3 py-2 font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <form method="POST" action="{{ route('admin.settings.general.update') }}" class="mt-6 space-y-6">
        @csrf

        <section x-show="tab === 'general'" x-cloak class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6">
                <div>
                    <label for="site_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Site Name</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Displayed in navigation, emails, audit trails, and health endpoints.</p>
                    <input id="site_name" name="site_name" type="text" value="{{ old('site_name', $form['site_name']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                </div>

                <div>
                    <label for="timezone" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Timezone</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Used for scheduling jobs, retention windows, and reporting snapshots.</p>
                    <select id="timezone" name="timezone" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $form['timezone']) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="app_url" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Application URL</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Base URL for signed URLs, notifications, and health callbacks.</p>
                    <input id="app_url" name="app_url" type="url" value="{{ old('app_url', $form['app_url']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                </div>
            </div>
        </section>

        <section x-show="tab === 'telephony'" x-cloak class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Telephony Provider</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Connection parameters used for polling, ingestion, and throttling.</p>
                </div>

                <div>
                    <label for="telephony_base_url" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Base URL</label>
                    <input id="telephony_base_url" name="telephony_base_url" type="url" value="{{ old('telephony_base_url', $form['telephony']['base_url']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                </div>

                <div>
                    <label for="telephony_auth_type" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Authentication Type</label>
                    <select id="telephony_auth_type" name="telephony_auth_type" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                        @foreach (['header' => 'Static Header', 'bearer' => 'Bearer Token'] as $option => $label)
                            <option value="{{ $option }}" @selected(old('telephony_auth_type', $form['telephony']['auth_type']) === $option)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="telephony_api_key" class="block text-sm font-medium text-slate-700 dark:text-slate-300">API Credential</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to keep the existing credential. Stored encrypted at rest.</p>
                    <input id="telephony_api_key" name="telephony_api_key" type="password" autocomplete="new-password" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="{{ $form['telephony']['api_key_set'] ? '••••••••' : 'Paste provider token' }}">
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="telephony_pagination_size" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Pagination Size</label>
                        <input id="telephony_pagination_size" name="telephony_pagination_size" type="number" min="1" value="{{ old('telephony_pagination_size', $form['telephony']['pagination_size']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div>
                        <label for="telephony_poll_window_days" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Poll Window (days)</label>
                        <input id="telephony_poll_window_days" name="telephony_poll_window_days" type="number" min="1" value="{{ old('telephony_poll_window_days', $form['telephony']['poll_window_days']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                </div>

                <div>
                    <label for="telephony_rate_limit_json" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Rate Limit Configuration (JSON)</label>
                    <textarea id="telephony_rate_limit_json" name="telephony_rate_limit_json" rows="4" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder='{"requests_per_minute":60}'>{{ old('telephony_rate_limit_json', $form['telephony']['rate_limit']) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Used by jobs for exponential backoff and adaptive throttling.</p>
                </div>
            </div>
        </section>

        <section x-show="tab === 'storage'" x-cloak class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Storage Backend</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Select the default storage target for downloaded media and derivative assets.</p>
                </div>

                <div class="flex gap-6">
                    @foreach (['local' => 'Local Disk', 's3' => 'S3 Compatible'] as $driver => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="radio" name="storage_driver" value="{{ $driver }}" @checked(old('storage_driver', $form['storage']['driver']) === $driver) @change="storageDriver='{{ $driver }}'">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <div x-show="storageDriver === 's3'" x-cloak class="space-y-4 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Credentials are masked. Leave blank to retain the stored values.</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="storage_s3_access_key" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Access Key</label>
                            <input id="storage_s3_access_key" name="storage_s3_access_key" type="password" autocomplete="off" placeholder="{{ $form['storage']['s3']['access_key_set'] ? 'Configured' : 'AKIA...' }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="storage_s3_secret" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Secret Key</label>
                            <input id="storage_s3_secret" name="storage_s3_secret" type="password" autocomplete="off" placeholder="{{ $form['storage']['s3']['secret_set'] ? 'Configured' : '••••••' }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="storage_s3_region" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Region</label>
                            <input id="storage_s3_region" name="storage_s3_region" type="text" value="{{ old('storage_s3_region', $form['storage']['s3']['region']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="storage_s3_bucket" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Bucket</label>
                            <input id="storage_s3_bucket" name="storage_s3_bucket" type="text" value="{{ old('storage_s3_bucket', $form['storage']['s3']['bucket']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div class="md:col-span-2">
                            <label for="storage_s3_prefix" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Prefix (optional)</label>
                            <input id="storage_s3_prefix" name="storage_s3_prefix" type="text" value="{{ old('storage_s3_prefix', $form['storage']['s3']['prefix']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'transcription'" x-cloak class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Transcription Engine</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Control Whisper API usage or local whisper.cpp execution.</p>
                </div>

                <div class="flex gap-6">
                    @foreach (['whisper_api' => 'Whisper API', 'whisper_cli' => 'Local whisper.cpp'] as $engine => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="radio" name="transcription_engine" value="{{ $engine }}" @checked(old('transcription_engine', $form['transcription']['engine']) === $engine) @change="transcriptionEngine='{{ $engine }}'">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <div x-show="transcriptionEngine === 'whisper_api'" x-cloak>
                    <label for="transcription_api_key" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Whisper API Key</label>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to keep the configured key.</p>
                    <input id="transcription_api_key" name="transcription_api_key" type="password" autocomplete="new-password" placeholder="{{ $form['transcription']['api_key_set'] ? 'Configured' : 'sk-...' }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                    <label for="transcription_api_timeout" class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-300">API Timeout (seconds)</label>
                    <input id="transcription_api_timeout" name="transcription_api_timeout" type="number" min="5" max="600" value="{{ old('transcription_api_timeout', $form['transcription']['api_timeout']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                </div>

                <div x-show="transcriptionEngine === 'whisper_cli'" x-cloak>
                    <label for="transcription_cli_path" class="block text-sm font-medium text-slate-700 dark:text-slate-300">whisper.cpp Binary Path</label>
                    <input id="transcription_cli_path" name="transcription_cli_path" type="text" value="{{ old('transcription_cli_path', $form['transcription']['cli_path']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div>
                            <label for="transcription_cli_model" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Model Identifier</label>
                            <input id="transcription_cli_model" name="transcription_cli_model" type="text" value="{{ old('transcription_cli_model', $form['transcription']['cli_model']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                        </div>
                        <div>
                            <label for="transcription_cli_threads" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Threads</label>
                            <input id="transcription_cli_threads" name="transcription_cli_threads" type="number" min="1" max="64" value="{{ old('transcription_cli_threads', $form['transcription']['cli_threads']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                        </div>
                        <div>
                            <label for="transcription_cli_timeout" class="block text-sm font-medium text-slate-700 dark:text-slate-300">CLI Timeout (seconds)</label>
                            <input id="transcription_cli_timeout" name="transcription_cli_timeout" type="number" min="60" max="7200" value="{{ old('transcription_cli_timeout', $form['transcription']['cli_timeout']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <label for="transcription_language" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Default Language</label>
                        <input id="transcription_language" name="transcription_language" type="text" value="{{ old('transcription_language', $form['transcription']['language']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div>
                        <label for="transcription_max_concurrent" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Max Concurrent Jobs</label>
                        <input id="transcription_max_concurrent" name="transcription_max_concurrent" type="number" min="1" value="{{ old('transcription_max_concurrent', $form['transcription']['max_concurrent']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div>
                        <label for="transcription_daily_limit_minutes" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Daily Usage Cap (minutes)</label>
                        <input id="transcription_daily_limit_minutes" name="transcription_daily_limit_minutes" type="number" min="0" max="1440" value="{{ old('transcription_daily_limit_minutes', $form['transcription']['daily_limit_minutes']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Zero disables the cap.</p>
                    </div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'notifications'" x-cloak class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Notifications</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Configure SMTP delivery and Slack alerts for health events.</p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="notifications_mail_host" class="block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP Host</label>
                        <input id="notifications_mail_host" name="notifications_mail_host" type="text" value="{{ old('notifications_mail_host', $form['notifications']['mail']['host']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div>
                        <label for="notifications_mail_port" class="block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP Port</label>
                        <input id="notifications_mail_port" name="notifications_mail_port" type="number" min="1" value="{{ old('notifications_mail_port', $form['notifications']['mail']['port']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div>
                        <label for="notifications_mail_username" class="block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP Username</label>
                        <input id="notifications_mail_username" name="notifications_mail_username" type="text" value="{{ old('notifications_mail_username', $form['notifications']['mail']['username']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="notifications_mail_password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP Password</label>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to retain the configured password.</p>
                        <input id="notifications_mail_password" name="notifications_mail_password" type="password" autocomplete="new-password" placeholder="{{ $form['notifications']['mail']['password_set'] ? 'Configured' : '••••••' }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="notifications_mail_encryption" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Encryption</label>
                        <input id="notifications_mail_encryption" name="notifications_mail_encryption" type="text" value="{{ old('notifications_mail_encryption', $form['notifications']['mail']['encryption']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="tls">
                    </div>
                    <div>
                        <label for="notifications_slack_webhook" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Slack Webhook (optional)</label>
                        <input id="notifications_slack_webhook" name="notifications_slack_webhook" type="url" value="{{ old('notifications_slack_webhook', $form['notifications']['slack']['webhook']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" placeholder="https://hooks.slack.com/services/...">
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label for="notifications_mail_from_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">From Name</label>
                        <input id="notifications_mail_from_name" name="notifications_mail_from_name" type="text" value="{{ old('notifications_mail_from_name', $form['notifications']['mail']['from_name']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                    <div>
                        <label for="notifications_mail_from_address" class="block text-sm font-medium text-slate-700 dark:text-slate-300">From Address</label>
                        <input id="notifications_mail_from_address" name="notifications_mail_from_address" type="email" value="{{ old('notifications_mail_from_address', $form['notifications']['mail']['from_address']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                    </div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'qa'" x-cloak class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6" x-data="qaBuilder({
                rubric: @js($qaInitialRubric),
                passThreshold: {{ (int) old('qa_pass_threshold', $form['qa']['pass_threshold']) }},
                rubricVersion: {{ (int) $form['qa']['rubric_version'] }},
            })">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">QA Rubric Builder</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Define scoring categories, question weights, and pass thresholds consumed by the QA workspace.</p>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">Current version: v{{ $form['qa']['rubric_version'] }}</div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="qa_pass_threshold" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Passing Threshold (%)</label>
                        <input id="qa_pass_threshold" name="qa_pass_threshold" type="number" min="0" max="100" x-model.number="passThreshold" value="{{ (int) old('qa_pass_threshold', $form['qa']['pass_threshold']) }}" class="mt-2 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        <p>Updating the rubric increments its version and refreshes the QA scoring UI the next time it loads.</p>
                    </div>
                </div>

                <input type="hidden" name="qa_rubric" :value="serializedRubric()">

                <div class="space-y-6">
                    <template x-for="(category, catIndex) in categories" :key="category.id">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40 p-4 space-y-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Category Name</label>
                                    <input type="text" x-model="categories[catIndex].name" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                                </div>
                                <div class="flex items-end gap-2">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Weight</label>
                                        <input type="number" step="1" x-model.number="categories[catIndex].weight" class="mt-1 w-24 rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                                    </div>
                                    <button type="button" class="mt-5 inline-flex items-center rounded-md border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-700 dark:text-rose-200 dark:hover:bg-rose-900/30" @click="removeCategory(catIndex)">Remove</button>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <template x-for="(question, questionIndex) in category.questions" :key="question.id">
                                    <div class="rounded-md border border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-900 p-4 space-y-3">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="flex-1">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Prompt</label>
                                                <input type="text" x-model="categories[catIndex].questions[questionIndex].prompt" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                                            </div>
                                            <div class="flex items-end gap-2">
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Type</label>
                                                    <select x-model="categories[catIndex].questions[questionIndex].type" class="mt-1 rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                                                        <option value="yes_no">Yes / No</option>
                                                        <option value="scale">Scale</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Weight</label>
                                                    <input type="number" step="0.5" x-model.number="categories[catIndex].questions[questionIndex].weight" class="mt-1 w-20 rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                                                </div>
                                                <button type="button" class="inline-flex items-center rounded-md border border-rose-300 px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-700 dark:text-rose-200 dark:hover:bg-rose-900/30" @click="removeQuestion(catIndex, questionIndex)">Remove</button>
                                            </div>
                                        </div>
                                        <div x-show="question.type === 'scale'" x-cloak class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Scale Minimum</label>
                                                <input type="number" step="0.5" x-model.number="categories[catIndex].questions[questionIndex].scale_min" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Scale Maximum</label>
                                                <input type="number" step="0.5" min="1" x-model.number="categories[catIndex].questions[questionIndex].scale_max" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500">
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <button type="button" class="inline-flex items-center rounded-md border border-brand-300 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-600 hover:bg-brand-100 dark:border-brand-500/60 dark:bg-brand-500/10 dark:text-brand-200" @click="addQuestion(catIndex)">Add Question</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-between">
                    <button type="button" class="inline-flex items-center rounded-md border border-brand-300 bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-100 dark:border-brand-500/60 dark:bg-brand-500/10 dark:text-brand-200" @click="addCategory()">Add Category</button>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Rubric changes are saved when you submit the settings form.</p>
                </div>
            </div>
        </section>

        <section x-show="tab === 'privacy'" x-cloak class="bg-white dark:bg-slate-800 shadow-sm rounded-lg border border-slate-200 dark:border-slate-700">
            <div class="p-6 space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Privacy &amp; Retention</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Govern how PII is presented in the UI and how long assets remain accessible.</p>
                </div>

                <div class="flex items-center gap-3">
                    <input id="privacy_pii_masking" name="privacy_pii_masking" type="checkbox" value="1" @checked(old('privacy_pii_masking', $form['privacy']['pii_masking'])) class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                    <label for="privacy_pii_masking" class="text-sm font-medium text-slate-700 dark:text-slate-300">Mask PII in UI tables and exports</label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Masking keeps only last four digits of phone numbers and truncates email addresses outside of authorized contexts.</p>

                <div class="max-w-xs">
                    <label for="privacy_retention_months" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Retention (months)</label>
                    <input id="privacy_retention_months" name="privacy_retention_months" type="number" min="1" value="{{ old('privacy_retention_months', $form['privacy']['retention_months']) }}" class="mt-2 block w-full rounded-md border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:border-brand-500 focus:ring-brand-500" required>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">Save Settings</button>
        </div>
    </form>
</div>

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('qaBuilder', (initial) => ({
                categories: [],
                passThreshold: initial.passThreshold ?? 80,
                rubricVersion: initial.rubricVersion ?? 1,
                init() {
                    const base = Array.isArray(initial.rubric) ? initial.rubric : [];
                    this.categories = base.map((category) => this.normalizeCategory(category));

                    if (this.categories.length === 0) {
                        this.addCategory();
                    }
                },
                normalizeCategory(category) {
                    const normalised = {
                        id: category.id || this.uuid(),
                        name: category.name || 'New Category',
                        weight: Number(category.weight ?? 0),
                        questions: Array.isArray(category.questions)
                            ? category.questions.map((question) => this.normalizeQuestion(question))
                            : [],
                    };

                    if (normalised.questions.length === 0) {
                        normalised.questions.push(this.normalizeQuestion({ prompt: 'New question', type: 'yes_no', weight: 1 }));
                    }

                    return normalised;
                },
                normalizeQuestion(question) {
                    const type = ['yes_no', 'scale'].includes(question.type) ? question.type : 'yes_no';

                    return {
                        id: question.id || this.uuid(),
                        prompt: question.prompt || 'Question',
                        type,
                        weight: Number(question.weight ?? 0),
                        scale_min: Number(question.scale_min ?? 0),
                        scale_max: Number(question.scale_max ?? 5) || 5,
                    };
                },
                uuid() {
                    return (typeof crypto !== 'undefined' && crypto.randomUUID)
                        ? crypto.randomUUID()
                        : 'qa-' + Math.random().toString(36).slice(2, 10);
                },
                addCategory() {
                    this.categories.push(this.normalizeCategory({
                        name: 'New Category',
                        weight: 0,
                        questions: [this.normalizeQuestion({ prompt: 'Did the agent greet the caller?', type: 'yes_no', weight: 1 })],
                    }));
                },
                removeCategory(index) {
                    this.categories.splice(index, 1);

                    if (this.categories.length === 0) {
                        this.addCategory();
                    }
                },
                addQuestion(catIndex) {
                    if (!this.categories[catIndex]) {
                        return;
                    }

                    this.categories[catIndex].questions.push(this.normalizeQuestion({
                        prompt: 'New question',
                        type: 'yes_no',
                        weight: 1,
                    }));
                },
                removeQuestion(catIndex, questionIndex) {
                    if (!this.categories[catIndex]) {
                        return;
                    }

                    this.categories[catIndex].questions.splice(questionIndex, 1);

                    if (this.categories[catIndex].questions.length === 0) {
                        this.addQuestion(catIndex);
                    }
                },
                serializedRubric() {
                    return JSON.stringify(this.categories);
                },
            }));
        });
    </script>
@endpush
