<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Display the administrator portal dashboard.
     */
    public function __invoke(): View
    {
        $data = $this->dashboardService->getAdminDashboardData();

        return view('admin.dashboard', $data);
    }
}
