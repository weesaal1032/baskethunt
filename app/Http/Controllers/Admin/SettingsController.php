<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
            'notifications.mail.host',
            'notifications.mail.port',
            'notifications.mail.username',
            'notifications.mail.password',
            'notifications.mail.encryption',
            'notifications.mail.from_address',
            'notifications.mail.from_name',
            'notifications.slack.webhook',
            'privacy.pii_masking',
            'privacy.retention_months',
        ];

        $values = $this->settings->getMany($keys);

        $telephonyRateLimit = $values['telephony.provider.rate_limit'] ?? '';
        if (is_array($telephonyRateLimit)) {
            $telephonyRateLimit = json_encode($telephonyRateLimit, JSON_PRETTY_PRINT);
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
            ],
            'transcription' => [
                'engine' => $values['transcription.engine'] ?? 'whisper_api',
                'api_key_set' => ! empty($values['transcription.api_key']),
                'cli_path' => $values['transcription.cli_path'] ?? '/usr/local/bin/whisper',
                'language' => $values['transcription.language'] ?? 'en',
                'max_concurrent' => (int) ($values['transcription.max_concurrent'] ?? 2),
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
                ],
                'slack' => [
                    'webhook' => $values['notifications.slack.webhook'] ?? '',
                ],
            ],
            'privacy' => [
                'pii_masking' => $this->normalizeBool($values['privacy.pii_masking'] ?? true),
                'retention_months' => (int) ($values['privacy.retention_months'] ?? 12),
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
            'transcription_engine' => ['required', Rule::in(['whisper_api', 'whisper_cli'])],
            'transcription_api_key' => ['nullable', 'string', 'max:512'],
            'transcription_cli_path' => ['nullable', 'string', 'max:255'],
            'transcription_language' => ['required', 'string', 'max:32'],
            'transcription_max_concurrent' => ['required', 'integer', 'min:1', 'max:64'],
            'notifications_mail_host' => ['required', 'string', 'max:191'],
            'notifications_mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'notifications_mail_username' => ['nullable', 'string', 'max:191'],
            'notifications_mail_password' => ['nullable', 'string', 'max:191'],
            'notifications_mail_encryption' => ['nullable', 'string', 'max:10'],
            'notifications_mail_from_address' => ['required', 'email'],
            'notifications_mail_from_name' => ['required', 'string', 'max:191'],
            'notifications_slack_webhook' => ['nullable', 'url'],
            'privacy_pii_masking' => ['nullable', 'boolean'],
            'privacy_retention_months' => ['required', 'integer', 'min:1', 'max:360'],
        ]);

        $validator->after(function ($validator) use ($existingSensitive): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();

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
            'transcription.engine' => $transcriptionEngine,
            'transcription.api_key' => $transcriptionApiKey,
            'transcription.cli_path' => $data['transcription_cli_path'] ?? null,
            'transcription.language' => $data['transcription_language'],
            'transcription.max_concurrent' => (int) $data['transcription_max_concurrent'],
            'notifications.mail.host' => $data['notifications_mail_host'],
            'notifications.mail.port' => (int) $data['notifications_mail_port'],
            'notifications.mail.username' => $data['notifications_mail_username'] ?? null,
            'notifications.mail.password' => $mailPassword,
            'notifications.mail.encryption' => $data['notifications_mail_encryption'] ?? null,
            'notifications.mail.from_address' => $data['notifications_mail_from_address'],
            'notifications.mail.from_name' => $data['notifications_mail_from_name'],
            'notifications.slack.webhook' => $data['notifications_slack_webhook'] ?? null,
            'privacy.pii_masking' => $piiMasking ? '1' : '0',
            'privacy.retention_months' => $retentionMonths,
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
            'transcription.drivers.whisper_cli.binary' => $data['transcription_cli_path'] ?? null,
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
            'callhub.transcription.engine' => $transcriptionEngine,
            'callhub.transcription.api_key' => $transcriptionApiKey,
            'callhub.transcription.cli_path' => $data['transcription_cli_path'] ?? null,
            'callhub.transcription.language' => $data['transcription_language'],
            'callhub.transcription.max_concurrent' => (int) $data['transcription_max_concurrent'],
            'callhub.notifications.mail.host' => $data['notifications_mail_host'],
            'callhub.notifications.mail.port' => (int) $data['notifications_mail_port'],
            'callhub.notifications.mail.username' => $data['notifications_mail_username'] ?? null,
            'callhub.notifications.mail.password' => $mailPassword,
            'callhub.notifications.mail.encryption' => $data['notifications_mail_encryption'] ?? null,
            'callhub.notifications.mail.from_address' => $data['notifications_mail_from_address'],
            'callhub.notifications.mail.from_name' => $data['notifications_mail_from_name'],
            'callhub.notifications.slack.webhook' => $data['notifications_slack_webhook'] ?? null,
            'callhub.privacy.pii_masking' => $piiMasking,
            'callhub.privacy.retention_months' => $retentionMonths,
        ]);

        return redirect()
            ->route('admin.settings.general')
            ->with('status', 'Settings updated successfully.');
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
