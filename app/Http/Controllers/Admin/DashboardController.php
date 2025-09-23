<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return view('layouts.base', [
            'title' => 'Admin Dashboard',
            'slot' => view('components.placeholder', [
                'heading' => 'Welcome to CallHub Admin',
                'body' => 'Configure providers, monitor jobs, and manage QA workflows here.'
            ])->render(),
        ]);
    }
}
