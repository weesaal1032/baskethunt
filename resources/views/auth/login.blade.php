<div class="mx-auto max-w-md space-y-8">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold">Sign in to CallHub</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Enter your email and password to continue. Two-factor authentication {{ $otpEnabled ? 'is enabled' : 'is currently disabled' }}.
        </p>
    </header>
    <form method="POST" action="{{ route('auth.login') }}" class="space-y-6">
        @csrf
        <div class="space-y-2">
            <label for="email" class="block text-sm font-medium">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="block w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
        </div>
        <div class="space-y-2">
            <div class="flex items-center justify-between text-sm">
                <label for="password" class="font-medium">Password</label>
                <a href="{{ route('auth.password.request') }}" class="text-brand-500 hover:underline">Forgot password?</a>
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="block w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
        </div>
        <div class="flex items-center gap-2">
            <input id="remember" type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
            <label for="remember" class="text-sm">Remember me on this device</label>
        </div>
        <button type="submit" class="w-full rounded bg-brand-500 px-4 py-2 text-white font-semibold hover:bg-brand-500/90">
            Continue
        </button>
    </form>
</div>
