<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOtpToken;
use App\Notifications\OtpCodeNotification;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    public function show(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('auth.otp')) {
            return redirect()->route('auth.login');
        }

        if (! config('callhub.security.email_otp.enabled')) {
            return redirect()->route('auth.login');
        }

        return view('layouts.base', [
            'title' => 'Verify one-time code',
            'slot' => view('auth.otp')->render(),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $payload = $request->session()->get('auth.otp');

        if (! $payload || ! config('callhub.security.email_otp.enabled')) {
            return redirect()->route('auth.login');
        }

        $this->ensureIsNotRateLimited($request, $payload['user_id']);

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $token = UserOtpToken::query()
            ->whereKey($payload['token_id'] ?? 0)
            ->where('user_id', $payload['user_id'])
            ->latest('id')
            ->first();

        if (! $token || $token->consumed_at || $token->expires_at->isPast()) {
            RateLimiter::hit($this->throttleKey($request, $payload['user_id']));

            throw ValidationException::withMessages([
                'code' => __('The one-time code is no longer valid.'),
            ]);
        }

        if (! Hash::check($request->string('code'), $token->code_hash)) {
            RateLimiter::hit($this->throttleKey($request, $payload['user_id']));

            throw ValidationException::withMessages([
                'code' => __('The provided one-time code is incorrect.'),
            ]);
        }

        $token->markConsumed();
        RateLimiter::clear($this->throttleKey($request, $payload['user_id']));
        if (! empty($payload['throttle_key'])) {
            RateLimiter::clear($payload['throttle_key']);
        }

        /** @var User $user */
        $user = User::findOrFail($payload['user_id']);

        $request->session()->forget('auth.otp');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'))->with('status', __('Logged in successfully.'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $payload = $request->session()->get('auth.otp');

        if (! $payload || ! config('callhub.security.email_otp.enabled')) {
            return redirect()->route('auth.login');
        }

        /** @var User $user */
        $user = User::findOrFail($payload['user_id']);

        $this->otpService->purgeActiveTokens($user);

        [$token, $code] = $this->otpService->generate(
            $user,
            (int) config('callhub.security.email_otp.expiry_minutes', 10)
        );

        $request->session()->put('auth.otp.token_id', $token->id);

        Notification::send($user, new OtpCodeNotification($code));

        return redirect()->route('auth.otp.show')->with('status', __('We sent a fresh one-time code.'));
    }

    protected function ensureIsNotRateLimited(Request $request, int $userId): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $userId), 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'code' => __('Too many attempts. Please try again later.'),
        ])->status(429);
    }

    protected function throttleKey(Request $request, int $userId): string
    {
        return implode('|', ['otp', $userId, $request->ip()]);
    }
}
