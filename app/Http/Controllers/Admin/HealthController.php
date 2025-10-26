<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Health\E2eHealthCheckService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HealthController extends Controller
{
    public function __construct(private readonly E2eHealthCheckService $checks)
    {
    }

    public function e2e(Request $request): View
    {
        $report = $this->checks->run($request->user());

        return view('layouts.base', [
            'title' => 'End-to-End Diagnostics',
            'slot' => view('admin.health.e2e', [
                'report' => $report,
            ])->render(),
        ]);
    }
}
