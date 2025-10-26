<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly OtpService $otpService)
    {
    }

    public function showLoginForm(): View
    {
        return view('layouts.base', [
            'title' => 'Sign in',
            'slot' => view('auth.login', [
                'otpEnabled' => (bool) config('callhub.security.email_otp.enabled'),
            ])->render(),
        ]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        /** @var User|null $user */
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($credentials['email'])])
            ->first();

        if (! $user || ! Auth::validate([
            'email' => $user->email,
            'password' => $credentials['password'],
        ])) {
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if ($user->status !== 'active') {
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

            throw ValidationException::withMessages([
                'email' => __('This account is not active.'),
            ]);
        }

        if (config('callhub.security.email_otp.enabled')) {
            $this->dispatchOtp($user, $request);

            return redirect()->route('auth.otp.show')->with('status', __('We emailed you a one-time passcode.'));
        }

        if (! Auth::attempt([
            'email' => $user->email,
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

            throw ValidationException::withMessages([
                'email' => __('Authentication failed.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'))->with('status', 'Logged in successfully.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login')->with('status', 'Logged out successfully.');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        $key = $this->throttleKey($request);
        $maxAttempts = (int) config('callhub.security.login.max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        abort(429, 'Too many login attempts.');
    }

    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }

    protected function decaySeconds(): int
    {
        return max((int) config('callhub.security.login.decay_seconds', 60), 1);
    }

    protected function dispatchOtp(User $user, Request $request): void
    {
        $this->otpService->purgeActiveTokens($user);

        [$token, $code] = $this->otpService->generate(
            $user,
            (int) config('callhub.security.email_otp.expiry_minutes', 10)
        );

        $request->session()->put('auth.otp', [
            'user_id' => $user->id,
            'token_id' => $token->id,
            'throttle_key' => $this->throttleKey($request),
        ]);

        Notification::send($user, new OtpCodeNotification($code));
    }
}
