<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Contracts\View\View;
use SplFileObject;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboard)
    {
    }

    public function index(): View
    {
        $metrics = $this->dashboard->metrics();

        return view('layouts.base', [
            'title' => 'Admin Dashboard',
            'slot' => view('admin.dashboard', [
                'metrics' => $metrics,
            ])->render(),
        ]);
    }

    public function logs(): View
    {
        $lines = $this->tailLog(storage_path('logs/laravel.log'), 200);

        return view('layouts.base', [
            'title' => 'Application Logs',
            'slot' => view('admin.logs', [
                'lines' => $lines,
            ])->render(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function tailLog(string $path, int $lines): array
    {
        if (! is_file($path)) {
            return [];
        }

        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $lines);
        $output = [];

        for ($line = $start; $line <= $lastLine; $line++) {
            $file->seek($line);
            $current = rtrim($file->current(), "\r\n");

            if ($current === '') {
                continue;
            }

            $output[] = $current;
        }

        return $output;
    }
}
