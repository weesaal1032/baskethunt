<?php

namespace App\Services\Notifications;

use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class NotificationService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, string>|null $channels
     */
    public function sendAlert(
        string $subject,
        string $message,
        array $context = [],
        ?string $throttleKey = null,
        int $throttleMinutes = 0,
        ?array $channels = null
    ): void {
        if ($throttleKey !== null && $this->isThrottled($throttleKey, $throttleMinutes)) {
            return;
        }

        $channels ??= ['mail', 'slack'];

        $this->sendMailAlert($subject, $message, $context, in_array('mail', $channels, true));
        $this->sendSlackAlert($subject, $message, $context, in_array('slack', $channels, true));

        if ($throttleKey !== null) {
            $this->settings->set($throttleKey, CarbonImmutable::now()->toIso8601String());
        }
    }

    /**
     * @param array<string, mixed> $metrics
     */
    public function sendDailySummary(array $metrics): void
    {
        $lines = [
            'Daily Operations Summary',
            '----------------------------------------',
        ];

        foreach ($metrics as $label => $value) {
            $lines[] = sprintf('%s: %s', Str::headline($label), $value);
        }

        $body = implode(PHP_EOL, $lines);

        $this->sendAlert(
            'CallHub Daily Summary',
            $body,
            ['type' => 'daily_summary'],
            'notifications.summary.last_sent_at',
            60 * 12
        );
    }

    private function isThrottled(string $key, int $minutes): bool
    {
        if ($minutes <= 0) {
            return false;
        }

        $raw = $this->settings->get($key);

        if (! is_string($raw) || $raw === '') {
            return false;
        }

        try {
            $last = CarbonImmutable::parse($raw);
        } catch (Throwable) {
            return false;
        }

        return $last->addMinutes($minutes)->isFuture();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendMailAlert(string $subject, string $message, array $context, bool $enabled): void
    {
        if (! $enabled) {
            return;
        }

        $recipients = $this->mailRecipients();

        if (empty($recipients)) {
            return;
        }

        $config = $this->mailConfiguration();

        if (empty($config['host']) || empty($config['port']) || empty($config['from_address']) || empty($config['from_name'])) {
            Log::warning('Skipping alert email due to incomplete SMTP configuration.');

            return;
        }

        config([
            'mail.mailers.callhub-alerts' => [
                'transport' => 'smtp',
                'host' => $config['host'],
                'port' => $config['port'],
                'username' => $config['username'],
                'password' => $config['password'],
                'encryption' => $config['encryption'],
            ],
            'mail.from.address' => $config['from_address'],
            'mail.from.name' => $config['from_name'],
        ]);

        try {
            Mail::mailer('callhub-alerts')->raw($message, function ($mail) use ($subject, $recipients, $context): void {
                $mail->subject($subject);
                $mail->to($recipients);

                if (! empty($context['reply_to']) && filter_var($context['reply_to'], FILTER_VALIDATE_EMAIL)) {
                    $mail->replyTo($context['reply_to']);
                }
            });
        } catch (Throwable $throwable) {
            Log::error('Failed to send alert email.', [
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendSlackAlert(string $subject, string $message, array $context, bool $enabled): void
    {
        if (! $enabled) {
            return;
        }

        $webhook = (string) ($this->settings->get('notifications.slack.webhook') ?? '');

        if ($webhook === '') {
            return;
        }

        $payload = [
            'text' => sprintf("*%s*\n%s", $subject, $message),
        ];

        if (! empty($context)) {
            $fields = [];

            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $fields[] = sprintf('%s: %s', Str::headline((string) $key), (string) $value);
                }
            }

            if (! empty($fields)) {
                $payload['text'] .= "\n".implode("\n", $fields);
            }
        }

        try {
            Http::timeout(5)->post($webhook, $payload);
        } catch (Throwable $throwable) {
            Log::error('Failed to send Slack alert.', [
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function mailRecipients(): array
    {
        $raw = $this->settings->get('notifications.mail.recipients');

        if (is_array($raw)) {
            $recipients = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $recipients = preg_split('/[,;]/', $raw) ?: [];
        } else {
            $fallback = config('callhub.notifications.mail.recipients');
            $recipients = is_array($fallback) ? $fallback : [];
        }

        return collect($recipients)
            ->map(fn ($value) => filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL) ?: null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mailConfiguration(): array
    {
        $keys = [
            'host' => 'notifications.mail.host',
            'port' => 'notifications.mail.port',
            'username' => 'notifications.mail.username',
            'password' => 'notifications.mail.password',
            'encryption' => 'notifications.mail.encryption',
            'from_address' => 'notifications.mail.from_address',
            'from_name' => 'notifications.mail.from_name',
        ];

        $values = [];

        foreach ($keys as $key => $settingKey) {
            $values[$key] = $this->settings->get($settingKey) ?? config('callhub.notifications.mail.'.Str::after($settingKey, 'notifications.mail.'));
        }

        $values['port'] = (int) ($values['port'] ?? 0);

        return $values;
    }
}
