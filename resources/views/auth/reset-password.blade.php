<div class="mx-auto max-w-md space-y-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold">Choose a new password</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Use a strong password that you have not used elsewhere.
        </p>
    </header>
    <form method="POST" action="{{ route('auth.password.update') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" />
        <input type="hidden" name="email" value="{{ $email }}" />
        <div class="space-y-2">
            <label for="password" class="block text-sm font-medium">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="block w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
        </div>
        <div class="space-y-2">
            <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="block w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
        </div>
        <button type="submit" class="w-full rounded bg-brand-500 px-4 py-2 text-white font-semibold hover:bg-brand-500/90">
            Update password
        </button>
    </form>
</div>
