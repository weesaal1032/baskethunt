<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallerController extends Controller
{
    public function index(Request $request): View
    {
        return view('layouts.base', [
            'title' => 'Install CallHub',
            'slot' => view('components.placeholder', [
                'heading' => 'Installation Wizard',
                'body' => 'Complete the initial configuration to start using CallHub.'
            ])->render(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'admin_email' => ['required', 'email'],
        ]);

        return redirect()->route('admin.dashboard')->with('status', 'Installation complete.');
    }
}
