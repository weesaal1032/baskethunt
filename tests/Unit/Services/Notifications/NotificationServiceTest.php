<?php

namespace Tests\Unit\Services\Notifications;

use App\Services\Notifications\NotificationService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_alert_delivers_mail_and_slack_with_throttle(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $settings->setMany([
            'notifications.mail.host' => 'smtp.mailgun.org',
            'notifications.mail.port' => 587,
            'notifications.mail.username' => 'mailer',
            'notifications.mail.password' => 'smtp-pass',
            'notifications.mail.encryption' => 'tls',
            'notifications.mail.from_address' => 'ops@callhub.test',
            'notifications.mail.from_name' => 'CallHub Ops',
            'notifications.mail.recipients' => ['ops@callhub.test'],
            'notifications.slack.webhook' => 'https://hooks.slack.com/services/T000/B000/AAA',
        ]);

        Mail::fake();
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        $service->sendAlert('Alert Subject', 'Alert body', [], 'notifications.test.alert', 60);
        $service->sendAlert('Alert Subject', 'Alert body', [], 'notifications.test.alert', 60);

        Mail::assertSent(function (Mailable $mail) {
            return $mail->hasTo('ops@callhub.test') && $mail->subject === 'Alert Subject';
        });
        Mail::assertSentCount(1);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://hooks.slack.com/services/T000/B000/AAA';
        });

        $this->assertNotNull($settings->get('notifications.test.alert'));
    }
}
