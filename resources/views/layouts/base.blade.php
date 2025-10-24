<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ dark: localStorage.getItem('theme') === 'dark' }" x-init="$watch('dark', value => localStorage.setItem('theme', value ? 'dark' : 'light'))" :class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <script>
        tailwind = window.tailwind || {};
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#0f62fe'
                        }
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans antialiased bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-slate-100">
    <div class="min-h-screen flex flex-col">
        <nav class="bg-white/70 dark:bg-slate-800/70 backdrop-blur border-b border-slate-200/80 dark:border-slate-700">
            <div class="mx-auto w-full max-w-6xl px-4 py-3 flex items-center justify-between">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-semibold">
                    <span class="h-3 w-3 rounded-full bg-brand-500"></span>
                    <span>{{ config('callhub.ui.branding') }}</span>
                </a>
                <div class="flex items-center gap-4 text-sm">
                    @unless($installerLocked ?? false)
                        <a href="{{ route('install.index') }}" class="hover:text-brand-500">Installer</a>
                    @endunless
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-brand-500">Admin</a>
                    @auth
                        <a href="{{ route('admin.recordings.index') }}" class="hover:text-brand-500">Recordings</a>
                        @if (auth()->user()?->role === 'admin')
                            <a href="{{ route('admin.providers.telephony.mapping') }}" class="hover:text-brand-500">Providers</a>
                            <a href="{{ route('admin.settings.general') }}" class="hover:text-brand-500">Settings</a>
                            <a href="{{ route('admin.health.e2e') }}" class="hover:text-brand-500">Health</a>
                            <a href="{{ route('admin.help') }}" class="hover:text-brand-500">Help</a>
                        @endif
                        <form method="POST" action="{{ route('auth.logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="hover:text-brand-500">Logout</button>
                        </form>
                    @else
                        <a href="{{ route('auth.login') }}" class="hover:text-brand-500">Auth</a>
                    @endauth
                    <button type="button" class="rounded-full border border-slate-300 dark:border-slate-600 px-3 py-1 flex items-center gap-2" @click="dark = !dark">
                        <span class="text-xs uppercase tracking-wide">Dark Mode</span>
                        <span x-text="dark ? 'On' : 'Off'"></span>
                    </button>
                </div>
            </div>
        </nav>
        <main class="flex-1">
            <div class="mx-auto max-w-6xl px-4 py-10">
                @if (session('status'))
                    <div class="mb-4 rounded border border-emerald-500 bg-emerald-50 px-4 py-3 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded border border-rose-500 bg-rose-50 px-4 py-3 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200">
                        <p class="font-semibold">Something went wrong:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {!! $slot ?? '' !!}
            </div>
        </main>
        <footer class="border-t border-slate-200 dark:border-slate-700 bg-white/60 dark:bg-slate-800/60 backdrop-blur">
            <div class="mx-auto max-w-6xl px-4 py-4 text-xs text-slate-500 dark:text-slate-400">
                &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
            </div>
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
