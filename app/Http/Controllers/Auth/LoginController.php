<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('layouts.base', [
            'title' => 'Sign in',
            'slot' => view('components.placeholder', [
                'heading' => 'Sign in to CallHub',
                'body' => 'Authentication UI coming soon.'
            ])->render(),
        ]);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        RateLimiter::hit($this->throttleKey($request));
        $this->ensureIsNotRateLimited($request);

        RateLimiter::clear($this->throttleKey($request));

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
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        abort(429, 'Too many login attempts.');
    }

    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }
}
