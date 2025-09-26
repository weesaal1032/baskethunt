<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserOtpToken;
use App\Notifications\OtpCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_password_when_otp_disabled(): void
    {
        config(['callhub.security.email_otp.enabled' => false]);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);

        $response = $this->post(route('auth.login'), [
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_must_complete_otp_when_enabled(): void
    {
        config(['callhub.security.email_otp.enabled' => true]);

        Notification::fake();

        $user = User::factory()->create([
            'email' => 'qa@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'qa',
        ]);

        $response = $this->post(route('auth.login'), [
            'email' => 'qa@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('auth.otp.show'));
        $this->assertGuest();

        $code = null;
        Notification::assertSentTo($user, OtpCodeNotification::class, function (OtpCodeNotification $notification) use (&$code) {
            $code = $notification->code();

            return true;
        });
        $token = UserOtpToken::first();
        $this->assertNotNull($token);
        $this->assertNotNull($code);

        $verify = $this->post(route('auth.otp.verify'), [
            'code' => $code,
        ]);

        $verify->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_incorrect_password_is_rejected(): void
    {
        config(['callhub.security.email_otp.enabled' => false]);

        $user = User::factory()->create([
            'email' => 'readonly@example.com',
            'password' => bcrypt('Password123!'),
            'role' => 'readonly',
        ]);

        $response = $this->from(route('auth.login'))->post(route('auth.login'), [
            'email' => 'readonly@example.com',
            'password' => 'invalid',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
