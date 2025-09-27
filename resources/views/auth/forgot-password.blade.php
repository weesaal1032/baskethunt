<div class="mx-auto max-w-md space-y-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold">Reset your password</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Provide your email address and we will send you a reset link.
        </p>
    </header>
    <form method="POST" action="{{ route('auth.password.email') }}" class="space-y-6">
        @csrf
        <div class="space-y-2">
            <label for="email" class="block text-sm font-medium">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="block w-full rounded border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
        </div>
        <button type="submit" class="w-full rounded bg-brand-500 px-4 py-2 text-white font-semibold hover:bg-brand-500/90">
            Email password reset link
        </button>
    </form>
    <div class="text-center text-sm">
        <a href="{{ route('auth.login') }}" class="text-brand-500 hover:underline">Back to sign in</a>
    </div>
</div>
