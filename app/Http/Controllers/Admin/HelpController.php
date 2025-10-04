<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recording;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HelpController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Recording::class);

        return view('layouts.base', [
            'title' => 'API & Exports',
            'slot' => view('admin.help', [
                'apiBaseUrl' => url('/api/internal'),
                'docsGeneratedAt' => now()->toIso8601String(),
                'claims' => $request->attributes->get('internal_api_claims'),
            ])->render(),
        ]);
    }
}
