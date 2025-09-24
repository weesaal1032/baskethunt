<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit(): View
    {
        $values = $this->settings->getMany([
            'app.name',
            'app.timezone',
            'app.url',
        ]);

        return view('layouts.base', [
            'title' => 'General Settings',
            'slot' => view('admin.settings.general', [
                'form' => [
                    'site_name' => $values['app.name'] ?? config('app.name'),
                    'timezone' => $values['app.timezone'] ?? config('app.timezone'),
                    'app_url' => $values['app.url'] ?? config('app.url'),
                ],
                'timezones' => timezone_identifiers_list(),
            ])->render(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'app_url' => ['required', 'url'],
        ]);

        $this->settings->setMany([
            'app.name' => $data['site_name'],
            'app.timezone' => $data['timezone'],
            'app.url' => $data['app_url'],
        ]);

        config([
            'app.name' => $data['site_name'],
            'app.timezone' => $data['timezone'],
            'app.url' => $data['app_url'],
        ]);

        return redirect()
            ->route('admin.settings.general')
            ->with('status', 'General settings updated successfully.');
    }
}
