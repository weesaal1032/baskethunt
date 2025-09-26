<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_settings_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.settings.general'));

        $response->assertOk();
        $response->assertSeeText('Administration Settings');
        $response->assertSee('Telephony Provider');
    }

    public function test_admin_can_update_all_settings_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $payload = [
            'site_name' => 'CallHub QA Ops',
            'timezone' => 'UTC',
            'app_url' => 'https://callhub.test',
            'telephony_base_url' => 'https://telephony.test/api',
            'telephony_auth_type' => 'bearer',
            'telephony_api_key' => 'secret-telephony',
            'telephony_pagination_size' => 200,
            'telephony_rate_limit_json' => json_encode(['requests_per_minute' => 60]),
            'telephony_poll_window_days' => 5,
            'storage_driver' => 'local',
            'storage_s3_access_key' => null,
            'storage_s3_secret' => null,
            'storage_s3_region' => null,
            'storage_s3_bucket' => null,
            'storage_s3_prefix' => null,
            'transcription_engine' => 'whisper_api',
            'transcription_api_key' => 'sk-test',
            'transcription_cli_path' => '/usr/local/bin/whisper',
            'transcription_language' => 'en',
            'transcription_max_concurrent' => 4,
            'notifications_mail_host' => 'smtp.mailgun.org',
            'notifications_mail_port' => 587,
            'notifications_mail_username' => 'mailer',
            'notifications_mail_password' => 'smtp-pass',
            'notifications_mail_encryption' => 'tls',
            'notifications_mail_from_address' => 'ops@callhub.test',
            'notifications_mail_from_name' => 'CallHub Ops',
            'notifications_slack_webhook' => 'https://hooks.slack.com/services/T000/B000/AAA',
            'privacy_pii_masking' => '1',
            'privacy_retention_months' => 18,
        ];

        $response = $this->actingAs($admin)->post(route('admin.settings.general.update'), $payload);

        $response->assertRedirect(route('admin.settings.general'));
        $response->assertSessionHas('status', 'Settings updated successfully.');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.name',
            'value' => 'CallHub QA Ops',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.timezone',
            'value' => 'UTC',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.url',
            'value' => 'https://callhub.test',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'telephony.provider.auth_type',
            'value' => 'bearer',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'telephony.provider.rate_limit',
            'value' => json_encode(['requests_per_minute' => 60]),
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'transcription.max_concurrent',
            'value' => '4',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'notifications.mail.password',
            'value' => 'smtp-pass',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'privacy.pii_masking',
            'value' => '1',
        ]);

        $this->assertSame('CallHub QA Ops', setting('app.name'));
    }

    public function test_secret_fields_are_retained_when_left_blank(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->seedSensitiveDefaults();

        $payload = [
            'site_name' => 'CallHub',
            'timezone' => 'UTC',
            'app_url' => 'https://callhub.test',
            'telephony_base_url' => 'https://telephony.test/api',
            'telephony_auth_type' => 'header',
            'telephony_api_key' => '',
            'telephony_pagination_size' => 100,
            'telephony_rate_limit_json' => null,
            'telephony_poll_window_days' => 7,
            'storage_driver' => 'local',
            'storage_s3_access_key' => '',
            'storage_s3_secret' => '',
            'storage_s3_region' => null,
            'storage_s3_bucket' => null,
            'storage_s3_prefix' => null,
            'transcription_engine' => 'whisper_api',
            'transcription_api_key' => '',
            'transcription_cli_path' => '/usr/local/bin/whisper',
            'transcription_language' => 'en',
            'transcription_max_concurrent' => 2,
            'notifications_mail_host' => 'smtp.mailgun.org',
            'notifications_mail_port' => 587,
            'notifications_mail_username' => 'mailer',
            'notifications_mail_password' => '',
            'notifications_mail_encryption' => 'tls',
            'notifications_mail_from_address' => 'ops@callhub.test',
            'notifications_mail_from_name' => 'Ops',
            'notifications_slack_webhook' => null,
            'privacy_pii_masking' => '1',
            'privacy_retention_months' => 12,
        ];

        $response = $this->actingAs($admin)->post(route('admin.settings.general.update'), $payload);

        $response->assertRedirect(route('admin.settings.general'));

        $this->assertDatabaseHas('settings', [
            'key' => 'telephony.provider.api_key',
            'value' => 'existing-telephony',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'notifications.mail.password',
            'value' => 'existing-smtp',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'transcription.api_key',
            'value' => 'existing-transcription',
        ]);
    }

    private function seedSensitiveDefaults(): void
    {
        \DB::table('settings')->insert([
            ['key' => 'telephony.provider.api_key', 'value' => 'existing-telephony'],
            ['key' => 'notifications.mail.password', 'value' => 'existing-smtp'],
            ['key' => 'transcription.api_key', 'value' => 'existing-transcription'],
            ['key' => 'storage.s3.key', 'value' => 'existing-access'],
            ['key' => 'storage.s3.secret', 'value' => 'existing-secret'],
        ]);
    }
}
