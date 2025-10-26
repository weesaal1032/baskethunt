<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit(): View
    {
        $keys = [
            'app.name',
            'app.timezone',
            'app.url',
            'telephony.provider.base_url',
            'telephony.provider.auth_type',
            'telephony.provider.api_key',
            'telephony.provider.pagination_size',
            'telephony.provider.rate_limit',
            'telephony.provider.poll_window_days',
            'storage.default',
            'storage.s3.key',
            'storage.s3.secret',
            'storage.s3.region',
            'storage.s3.bucket',
            'storage.s3.prefix',
            'transcription.engine',
            'transcription.api_key',
            'transcription.cli_path',
            'transcription.language',
            'transcription.max_concurrent',
            'transcription.api_timeout',
            'transcription.cli_model',
            'transcription.cli_threads',
            'transcription.cli_timeout',
            'transcription.daily_limit_minutes',
            'notifications.mail.host',
            'notifications.mail.port',
            'notifications.mail.username',
            'notifications.mail.password',
            'notifications.mail.encryption',
            'notifications.mail.from_address',
            'notifications.mail.from_name',
            'notifications.mail.recipients',
            'notifications.slack.webhook',
            'notifications.transcription.backlog_threshold',
            'privacy.pii_masking',
            'privacy.retention_months',
            'privacy.deletion_grace_days',
            'storage.alert.local_percent',
            'storage.alert.s3_gb',
            'qa.rubric',
            'qa.pass_threshold',
            'qa.rubric_version',
        ];

        $values = $this->settings->getMany($keys);

        $telephonyRateLimit = $values['telephony.provider.rate_limit'] ?? '';
        if (is_array($telephonyRateLimit)) {
            $telephonyRateLimit = json_encode($telephonyRateLimit, JSON_PRETTY_PRINT);
        }

        $mailRecipientsValue = $values['notifications.mail.recipients'] ?? config('callhub.notifications.mail.recipients', []);
        if (is_string($mailRecipientsValue) && $mailRecipientsValue !== '') {
            $mailRecipientsValue = array_filter(array_map('trim', explode(',', $mailRecipientsValue)));
        }
        if (! is_array($mailRecipientsValue)) {
            $mailRecipientsValue = [];
        }
        $mailRecipients = implode(', ', $mailRecipientsValue);

        $qaRubric = $values['qa.rubric'] ?? config('callhub.qa.rubric');
        if (is_string($qaRubric) && $qaRubric !== '') {
            $qaRubric = json_decode($qaRubric, true);
        }

        if (! is_array($qaRubric)) {
            $qaRubric = config('callhub.qa.rubric', []);
        }

        $form = [
            'site_name' => $values['app.name'] ?? config('app.name'),
            'timezone' => $values['app.timezone'] ?? config('app.timezone'),
            'app_url' => $values['app.url'] ?? config('app.url'),
            'telephony' => [
                'base_url' => $values['telephony.provider.base_url'] ?? '',
                'auth_type' => $values['telephony.provider.auth_type'] ?? 'header',
                'pagination_size' => (int) ($values['telephony.provider.pagination_size'] ?? 100),
                'rate_limit' => $telephonyRateLimit,
                'poll_window_days' => (int) ($values['telephony.provider.poll_window_days'] ?? 7),
                'api_key_set' => ! empty($values['telephony.provider.api_key']),
            ],
            'storage' => [
                'driver' => $values['storage.default'] ?? 'local',
                's3' => [
                    'access_key_set' => ! empty($values['storage.s3.key']),
                    'secret_set' => ! empty($values['storage.s3.secret']),
                    'region' => $values['storage.s3.region'] ?? '',
                    'bucket' => $values['storage.s3.bucket'] ?? '',
                    'prefix' => $values['storage.s3.prefix'] ?? '',
                ],
                'alert' => [
                    'local_percent' => (float) ($values['storage.alert.local_percent'] ?? config('callhub.storage.alert.local_percent', 80)),
                    's3_gb' => (int) ($values['storage.alert.s3_gb'] ?? config('callhub.storage.alert.s3_gb', 0)),
                ],
            ],
            'transcription' => [
                'engine' => $values['transcription.engine'] ?? 'whisper_api',
                'api_key_set' => ! empty($values['transcription.api_key']),
                'cli_path' => $values['transcription.cli_path'] ?? '/usr/local/bin/whisper',
                'language' => $values['transcription.language'] ?? 'en',
                'max_concurrent' => (int) ($values['transcription.max_concurrent'] ?? 2),
                'api_timeout' => (int) ($values['transcription.api_timeout'] ?? 30),
                'cli_model' => $values['transcription.cli_model'] ?? 'base.en',
                'cli_threads' => (int) ($values['transcription.cli_threads'] ?? 4),
                'cli_timeout' => (int) ($values['transcription.cli_timeout'] ?? 600),
                'daily_limit_minutes' => (int) ($values['transcription.daily_limit_minutes'] ?? 0),
            ],
            'notifications' => [
                'mail' => [
                    'host' => $values['notifications.mail.host'] ?? config('mail.mailers.smtp.host'),
                    'port' => (int) ($values['notifications.mail.port'] ?? config('mail.mailers.smtp.port', 587)),
                    'username' => $values['notifications.mail.username'] ?? config('mail.mailers.smtp.username'),
                    'password_set' => ! empty($values['notifications.mail.password']),
                    'encryption' => $values['notifications.mail.encryption'] ?? config('mail.mailers.smtp.encryption'),
                    'from_address' => $values['notifications.mail.from_address'] ?? config('mail.from.address'),
                    'from_name' => $values['notifications.mail.from_name'] ?? config('mail.from.name'),
                    'recipients' => $mailRecipients,
                ],
                'slack' => [
                    'webhook' => $values['notifications.slack.webhook'] ?? '',
                ],
                'transcription' => [
                    'backlog_threshold' => (int) ($values['notifications.transcription.backlog_threshold'] ?? config('callhub.notifications.transcription.backlog_threshold', 20)),
                ],
            ],
            'privacy' => [
                'pii_masking' => $this->normalizeBool($values['privacy.pii_masking'] ?? true),
                'retention_months' => (int) ($values['privacy.retention_months'] ?? 12),
                'deletion_grace_days' => (int) ($values['privacy.deletion_grace_days'] ?? 14),
            ],
            'qa' => [
                'rubric' => $qaRubric,
                'pass_threshold' => (int) ($values['qa.pass_threshold'] ?? config('callhub.qa.pass_threshold', 80)),
                'rubric_version' => (int) ($values['qa.rubric_version'] ?? config('callhub.qa.rubric_version', 1)),
            ],
        ];

        return view('layouts.base', [
            'title' => 'Settings',
            'slot' => view('admin.settings.general', [
                'form' => $form,
                'timezones' => timezone_identifiers_list(),
            ])->render(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $existingSensitive = $this->settings->getMany([
            'telephony.provider.api_key',
            'storage.s3.key',
            'storage.s3.secret',
            'transcription.api_key',
            'notifications.mail.password',
        ]);

        $currentRubric = $this->settings->get('qa.rubric');
        if (is_string($currentRubric) && $currentRubric !== '') {
            $currentRubric = json_decode($currentRubric, true);
        }
        if (! is_array($currentRubric)) {
            $currentRubric = config('callhub.qa.rubric', []);
        }

        $currentRubricVersion = (int) ($this->settings->get('qa.rubric_version') ?? config('callhub.qa.rubric_version', 1));
        $rubricPayload = [];

        $mailRecipientList = [];

        $validator = Validator::make($request->all(), [
            'site_name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'app_url' => ['required', 'url'],
            'telephony_base_url' => ['required', 'url'],
            'telephony_auth_type' => ['required', Rule::in(['header', 'bearer'])],
            'telephony_api_key' => ['nullable', 'string', 'max:512'],
            'telephony_pagination_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'telephony_rate_limit_json' => ['nullable', 'json'],
            'telephony_poll_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'storage_driver' => ['required', Rule::in(['local', 's3'])],
            'storage_s3_access_key' => ['nullable', 'string', 'max:191'],
            'storage_s3_secret' => ['nullable', 'string', 'max:191'],
            'storage_s3_region' => ['nullable', 'string', 'max:191'],
            'storage_s3_bucket' => ['nullable', 'string', 'max:191'],
            'storage_s3_prefix' => ['nullable', 'string', 'max:191'],
            'storage_local_alert_percent' => ['required', 'numeric', 'min:10', 'max:100'],
            'storage_s3_alert_gb' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'transcription_engine' => ['required', Rule::in(['whisper_api', 'whisper_cli'])],
            'transcription_api_key' => ['nullable', 'string', 'max:512'],
            'transcription_cli_path' => ['nullable', 'string', 'max:255'],
            'transcription_language' => ['required', 'string', 'max:32'],
            'transcription_max_concurrent' => ['required', 'integer', 'min:1', 'max:64'],
            'transcription_api_timeout' => ['required', 'integer', 'min:5', 'max:600'],
            'transcription_cli_model' => ['required', 'string', 'max:191'],
            'transcription_cli_threads' => ['required', 'integer', 'min:1', 'max:64'],
            'transcription_cli_timeout' => ['required', 'integer', 'min:60', 'max:7200'],
            'transcription_daily_limit_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notifications_mail_host' => ['required', 'string', 'max:191'],
            'notifications_mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'notifications_mail_username' => ['nullable', 'string', 'max:191'],
            'notifications_mail_password' => ['nullable', 'string', 'max:191'],
            'notifications_mail_encryption' => ['nullable', 'string', 'max:10'],
            'notifications_mail_from_address' => ['required', 'email'],
            'notifications_mail_from_name' => ['required', 'string', 'max:191'],
            'notifications_mail_recipients' => ['required', 'string'],
            'notifications_slack_webhook' => ['nullable', 'url'],
            'notifications_transcription_backlog_threshold' => ['required', 'integer', 'min:1', 'max:5000'],
            'privacy_pii_masking' => ['nullable', 'boolean'],
            'privacy_retention_months' => ['required', 'integer', 'min:1', 'max:360'],
            'privacy_deletion_grace_days' => ['required', 'integer', 'min:1', 'max:365'],
            'qa_rubric' => ['required', 'string'],
            'qa_pass_threshold' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($existingSensitive, &$rubricPayload, &$mailRecipientList): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();

            $rawRecipients = (string) ($data['notifications_mail_recipients'] ?? '');
            $emails = array_filter(array_map('trim', preg_split('/[,;]/', $rawRecipients) ?: []));

            if (empty($emails)) {
                $validator->errors()->add('notifications_mail_recipients', 'Provide at least one notification recipient.');

                return;
            }

            foreach ($emails as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('notifications_mail_recipients', sprintf('Invalid email address: %s', $email));

                    return;
                }
            }

            $mailRecipientList = array_values(array_unique($emails));

            if (($data['storage_driver'] ?? 'local') === 's3') {
                if ($this->secretMissing($data['storage_s3_access_key'] ?? null, $existingSensitive['storage.s3.key'] ?? null)) {
                    $validator->errors()->add('storage_s3_access_key', 'The S3 access key is required when using the S3 backend.');
                }

                if ($this->secretMissing($data['storage_s3_secret'] ?? null, $existingSensitive['storage.s3.secret'] ?? null)) {
                    $validator->errors()->add('storage_s3_secret', 'The S3 secret is required when using the S3 backend.');
                }

                if (empty($data['storage_s3_region'])) {
                    $validator->errors()->add('storage_s3_region', 'The S3 region is required when using the S3 backend.');
                }

                if (empty($data['storage_s3_bucket'])) {
                    $validator->errors()->add('storage_s3_bucket', 'The S3 bucket is required when using the S3 backend.');
                }
            }

            if (($data['transcription_engine'] ?? 'whisper_api') === 'whisper_api'
                && $this->secretMissing($data['transcription_api_key'] ?? null, $existingSensitive['transcription.api_key'] ?? null)
            ) {
                $validator->errors()->add('transcription_api_key', 'The Whisper API key is required when using the Whisper API engine.');
            }

            if ($this->secretMissing($data['notifications_mail_password'] ?? null, $existingSensitive['notifications.mail.password'] ?? null)) {
                $validator->errors()->add('notifications_mail_password', 'An SMTP password is required to send email.');
            }

            if ($this->secretMissing($data['telephony_api_key'] ?? null, $existingSensitive['telephony.provider.api_key'] ?? null)) {
                $validator->errors()->add('telephony_api_key', 'A telephony API key is required for provider requests.');
            }

            try {
                $decoded = json_decode($data['qa_rubric'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $validator->errors()->add('qa_rubric', 'QA rubric JSON is invalid: ' . $exception->getMessage());

                return;
            }

            if (! is_array($decoded)) {
                $validator->errors()->add('qa_rubric', 'QA rubric must be an array.');

                return;
            }

            $sanitisedRubric = $this->sanitizeRubric($decoded);

            if (empty($sanitisedRubric)) {
                $validator->errors()->add('qa_rubric', 'At least one category with one question is required for the QA rubric.');

                return;
            }

            $rubricPayload = $sanitisedRubric;
        });

        if ($validator->fails()) {
            return redirect()->route('admin.settings.general')->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $telephonyRateLimit = null;
        if (! empty($data['telephony_rate_limit_json'])) {
            $telephonyRateLimit = json_decode($data['telephony_rate_limit_json'], true) ?? null;
        }

        $storageDriver = $data['storage_driver'];
        $transcriptionEngine = $data['transcription_engine'];

        $telephonyApiKey = $this->retainSecret($data['telephony_api_key'] ?? null, $existingSensitive['telephony.provider.api_key'] ?? null);
        $s3AccessKey = $this->retainSecret($data['storage_s3_access_key'] ?? null, $existingSensitive['storage.s3.key'] ?? null);
        $s3Secret = $this->retainSecret($data['storage_s3_secret'] ?? null, $existingSensitive['storage.s3.secret'] ?? null);
        $transcriptionApiKey = $this->retainSecret($data['transcription_api_key'] ?? null, $existingSensitive['transcription.api_key'] ?? null);
        $mailPassword = $this->retainSecret($data['notifications_mail_password'] ?? null, $existingSensitive['notifications.mail.password'] ?? null);

        $piiMasking = $request->boolean('privacy_pii_masking');
        $retentionMonths = (int) $data['privacy_retention_months'];
        $deletionGraceDays = (int) $data['privacy_deletion_grace_days'];
        $qaPassThreshold = (int) $data['qa_pass_threshold'];
        $rubric = $rubricPayload;
        $rubricChanged = json_encode($currentRubric) !== json_encode($rubric);
        $rubricVersion = $rubricChanged ? $currentRubricVersion + 1 : $currentRubricVersion;
        $localAlertPercent = (float) $data['storage_local_alert_percent'];
        $s3AlertGb = (int) ($data['storage_s3_alert_gb'] ?? 0);
        $transcriptionBacklogThreshold = (int) $data['notifications_transcription_backlog_threshold'];

        $settingsPayload = [
            'app.name' => $data['site_name'],
            'app.timezone' => $data['timezone'],
            'app.url' => $data['app_url'],
            'telephony.provider.base_url' => $data['telephony_base_url'],
            'telephony.provider.auth_type' => $data['telephony_auth_type'],
            'telephony.provider.api_key' => $telephonyApiKey,
            'telephony.provider.pagination_size' => (int) $data['telephony_pagination_size'],
            'telephony.provider.rate_limit' => $telephonyRateLimit,
            'telephony.provider.poll_window_days' => (int) $data['telephony_poll_window_days'],
            'storage.default' => $storageDriver,
            'storage.s3.key' => $s3AccessKey,
            'storage.s3.secret' => $s3Secret,
            'storage.s3.region' => $data['storage_s3_region'] ?? null,
            'storage.s3.bucket' => $data['storage_s3_bucket'] ?? null,
            'storage.s3.prefix' => $data['storage_s3_prefix'] ?? null,
            'storage.alert.local_percent' => $localAlertPercent,
            'storage.alert.s3_gb' => $s3AlertGb,
            'transcription.engine' => $transcriptionEngine,
            'transcription.api_key' => $transcriptionApiKey,
            'transcription.cli_path' => $data['transcription_cli_path'] ?? null,
            'transcription.language' => $data['transcription_language'],
            'transcription.max_concurrent' => (int) $data['transcription_max_concurrent'],
            'transcription.api_timeout' => (int) $data['transcription_api_timeout'],
            'transcription.cli_model' => $data['transcription_cli_model'],
            'transcription.cli_threads' => (int) $data['transcription_cli_threads'],
            'transcription.cli_timeout' => (int) $data['transcription_cli_timeout'],
            'transcription.daily_limit_minutes' => (int) ($data['transcription_daily_limit_minutes'] ?? 0),
            'notifications.mail.host' => $data['notifications_mail_host'],
            'notifications.mail.port' => (int) $data['notifications_mail_port'],
            'notifications.mail.username' => $data['notifications_mail_username'] ?? null,
            'notifications.mail.password' => $mailPassword,
            'notifications.mail.encryption' => $data['notifications_mail_encryption'] ?? null,
            'notifications.mail.from_address' => $data['notifications_mail_from_address'],
            'notifications.mail.from_name' => $data['notifications_mail_from_name'],
            'notifications.mail.recipients' => $mailRecipientList,
            'notifications.slack.webhook' => $data['notifications_slack_webhook'] ?? null,
            'notifications.transcription.backlog_threshold' => $transcriptionBacklogThreshold,
            'privacy.pii_masking' => $piiMasking ? '1' : '0',
            'privacy.retention_months' => $retentionMonths,
            'privacy.deletion_grace_days' => $deletionGraceDays,
            'qa.rubric' => $rubric,
            'qa.pass_threshold' => $qaPassThreshold,
            'qa.rubric_version' => $rubricVersion,
        ];

        $this->settings->setMany($settingsPayload);

        config([
            'app.name' => $data['site_name'],
            'app.timezone' => $data['timezone'],
            'app.url' => $data['app_url'],
            'filesystems.default' => $storageDriver,
            'filesystems.disks.s3.key' => $s3AccessKey,
            'filesystems.disks.s3.secret' => $s3Secret,
            'filesystems.disks.s3.region' => $data['storage_s3_region'] ?? null,
            'filesystems.disks.s3.bucket' => $data['storage_s3_bucket'] ?? null,
            'filesystems.disks.s3.root' => $data['storage_s3_prefix'] ?? null,
            'transcription.default' => $transcriptionEngine,
            'transcription.drivers.whisper_api.api_key' => $transcriptionApiKey,
            'transcription.drivers.whisper_api.timeout' => (int) $data['transcription_api_timeout'],
            'transcription.drivers.whisper_cli.binary' => $data['transcription_cli_path'] ?? null,
            'transcription.drivers.whisper_cli.model' => $data['transcription_cli_model'],
            'transcription.drivers.whisper_cli.threads' => (int) $data['transcription_cli_threads'],
            'transcription.drivers.whisper_cli.timeout' => (int) $data['transcription_cli_timeout'],
            'mail.mailers.smtp.host' => $data['notifications_mail_host'],
            'mail.mailers.smtp.port' => (int) $data['notifications_mail_port'],
            'mail.mailers.smtp.username' => $data['notifications_mail_username'] ?? null,
            'mail.mailers.smtp.password' => $mailPassword,
            'mail.mailers.smtp.encryption' => $data['notifications_mail_encryption'] ?? null,
            'mail.from.address' => $data['notifications_mail_from_address'],
            'mail.from.name' => $data['notifications_mail_from_name'],
            'services.slack.webhook' => $data['notifications_slack_webhook'] ?? null,
            'callhub.telephony.base_url' => $data['telephony_base_url'],
            'callhub.telephony.auth_type' => $data['telephony_auth_type'],
            'callhub.telephony.pagination_size' => (int) $data['telephony_pagination_size'],
            'callhub.telephony.rate_limit' => $telephonyRateLimit,
            'callhub.telephony.poll_window_days' => (int) $data['telephony_poll_window_days'],
            'callhub.storage.default' => $storageDriver,
            'callhub.storage.s3.access_key' => $s3AccessKey,
            'callhub.storage.s3.secret_key' => $s3Secret,
            'callhub.storage.s3.region' => $data['storage_s3_region'] ?? null,
            'callhub.storage.s3.bucket' => $data['storage_s3_bucket'] ?? null,
            'callhub.storage.s3.prefix' => $data['storage_s3_prefix'] ?? null,
            'callhub.storage.alert.local_percent' => $localAlertPercent,
            'callhub.storage.alert.s3_gb' => $s3AlertGb,
            'callhub.transcription.engine' => $transcriptionEngine,
            'callhub.transcription.api_key' => $transcriptionApiKey,
            'callhub.transcription.cli_path' => $data['transcription_cli_path'] ?? null,
            'callhub.transcription.language' => $data['transcription_language'],
            'callhub.transcription.max_concurrent' => (int) $data['transcription_max_concurrent'],
            'callhub.transcription.api_timeout' => (int) $data['transcription_api_timeout'],
            'callhub.transcription.cli_model' => $data['transcription_cli_model'],
            'callhub.transcription.cli_threads' => (int) $data['transcription_cli_threads'],
            'callhub.transcription.cli_timeout' => (int) $data['transcription_cli_timeout'],
            'callhub.transcription.daily_limit_minutes' => (int) ($data['transcription_daily_limit_minutes'] ?? 0),
            'callhub.notifications.mail.host' => $data['notifications_mail_host'],
            'callhub.notifications.mail.port' => (int) $data['notifications_mail_port'],
            'callhub.notifications.mail.username' => $data['notifications_mail_username'] ?? null,
            'callhub.notifications.mail.password' => $mailPassword,
            'callhub.notifications.mail.encryption' => $data['notifications_mail_encryption'] ?? null,
            'callhub.notifications.mail.from_address' => $data['notifications_mail_from_address'],
            'callhub.notifications.mail.from_name' => $data['notifications_mail_from_name'],
            'callhub.notifications.mail.recipients' => $mailRecipientList,
            'callhub.notifications.slack.webhook' => $data['notifications_slack_webhook'] ?? null,
            'callhub.notifications.transcription.backlog_threshold' => $transcriptionBacklogThreshold,
            'callhub.privacy.pii_masking' => $piiMasking,
            'callhub.privacy.retention_months' => $retentionMonths,
            'callhub.privacy.deletion_grace_days' => $deletionGraceDays,
            'callhub.qa.rubric' => $rubric,
            'callhub.qa.pass_threshold' => $qaPassThreshold,
            'callhub.qa.rubric_version' => $rubricVersion,
        ]);

        return redirect()
            ->route('admin.settings.general')
            ->with('status', 'Settings updated successfully.');
    }

    public function sendTestNotification(Request $request, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in(['mail', 'slack'])],
        ]);

        $channel = $data['channel'];

        $notifications->sendAlert(
            sprintf('CallHub %s Notification Test', ucfirst($channel)),
            'This is a test notification triggered from the settings panel to verify delivery.',
            ['triggered_by' => optional($request->user())->email],
            null,
            0,
            [$channel]
        );

        return redirect()
            ->route('admin.settings.general')
            ->with('status', sprintf('%s notification dispatched for testing.', ucfirst($channel)));
    }

    private function retainSecret(?string $input, mixed $existing): ?string
    {
        $input = $this->trimStringNullable($input);

        if ($input === null) {
            return $this->trimStringNullable($existing);
        }

        return $input;
    }

    private function secretMissing(?string $input, mixed $existing): bool
    {
        $input = $this->trimStringNullable($input);
        $existing = $this->trimStringNullable($existing);

        return $input === null && $existing === null;
    }

    private function trimStringNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<int, mixed> $rubric
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeRubric(array $rubric): array
    {
        $categories = [];

        foreach ($rubric as $category) {
            if (! is_array($category)) {
                continue;
            }

            $name = trim((string) ($category['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $categoryId = (string) ($category['id'] ?? Str::uuid()->toString());
            $weight = isset($category['weight']) ? (float) $category['weight'] : 0.0;
            $questions = [];

            foreach ($category['questions'] ?? [] as $question) {
                if (! is_array($question)) {
                    continue;
                }

                $prompt = trim((string) ($question['prompt'] ?? ''));

                if ($prompt === '') {
                    continue;
                }

                $questionId = (string) ($question['id'] ?? Str::uuid()->toString());
                $type = in_array($question['type'] ?? 'yes_no', ['yes_no', 'scale'], true)
                    ? $question['type']
                    : 'yes_no';
                $questionWeight = isset($question['weight']) ? (float) $question['weight'] : 0.0;

                $questionData = [
                    'id' => $questionId,
                    'prompt' => $prompt,
                    'type' => $type,
                    'weight' => $questionWeight,
                ];

                if ($type === 'scale') {
                    $questionData['scale_min'] = isset($question['scale_min']) ? (float) $question['scale_min'] : 0.0;
                    $questionData['scale_max'] = max(1.0, isset($question['scale_max']) ? (float) $question['scale_max'] : 5.0);
                }

                $questions[] = $questionData;
            }

            if ($questions === []) {
                continue;
            }

            $categories[] = [
                'id' => $categoryId,
                'name' => $name,
                'weight' => $weight,
                'questions' => array_values($questions),
            ];
        }

        return $categories;
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $value;
    }
}
