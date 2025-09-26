<div class="mx-auto max-w-md space-y-8">
    <header class="space-y-2 text-center">
        <h1 class="text-2xl font-semibold">Check your email</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Enter the six-digit code we sent to your inbox to finish signing in.
        </p>
    </header>
    <form method="POST" action="{{ route('auth.otp.verify') }}" class="space-y-6">
        @csrf
        <div class="space-y-2">
            <label for="code" class="block text-sm font-medium">One-time code</label>
            <input id="code" type="text" inputmode="numeric" name="code" value="{{ old('code') }}" required maxlength="6" minlength="6" class="block w-full rounded border-slate-300 text-center text-lg tracking-widest dark:border-slate-600 dark:bg-slate-800" />
        </div>
        <button type="submit" class="w-full rounded bg-brand-500 px-4 py-2 text-white font-semibold hover:bg-brand-500/90">
            Verify and continue
        </button>
    </form>
    <form method="POST" action="{{ route('auth.otp.resend') }}" class="text-center text-sm">
        @csrf
        <button type="submit" class="text-brand-500 hover:underline">Resend code</button>
    </form>
</div>
