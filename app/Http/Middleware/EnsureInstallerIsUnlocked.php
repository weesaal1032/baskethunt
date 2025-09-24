<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallerIsUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if (File::exists(storage_path('installed.flag'))) {
            abort(403, 'Installer is locked.');
        }

        return $next($request);
    }
}
