<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your CallHub one-time passcode')
            ->line('Use the following verification code to complete your sign-in:')
            ->line($this->code)
            ->line('This code expires in '.config('callhub.security.email_otp.expiry_minutes').' minutes.')
            ->line('If you did not attempt to sign in, you can safely ignore this email.');
    }

    public function code(): string
    {
        return $this->code;
    }
}
