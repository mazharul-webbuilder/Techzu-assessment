<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Agent\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Display the agent workstation dashboard.
     */
    public function __invoke(): View
    {
        $agent = auth('agent')->user();
        $data = $this->dashboardService->getAgentDashboardData($agent);

        return view('agent.dashboard', $data);
    }
}
