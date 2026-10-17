<?php

namespace App\Services\Admin;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Service managing administrator command center metrics and live ticket queue data.
 */
class DashboardService
{
    /**
     * Get aggregate metrics, live ticket feed, and system metrics for admin dashboard.
     *
     * @return array{stats: object, recentTickets: \Illuminate\Database\Eloquent\Collection, agentsCount: int, categoriesCount: int}
     */
    public function getAdminDashboardData(): array
    {
        $now = Carbon::now();

        $stats = Ticket::selectRaw("
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as open_total,
            COALESCE(SUM(CASE WHEN assigned_agent_id IS NULL AND status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as unassigned,
            COALESCE(SUM(CASE WHEN priority = 'critical' AND status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as critical_open,
            COALESCE(SUM(CASE WHEN sla_due_at <= ? AND status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as breached,
            COALESCE(SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? AND status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as approaching,
            COALESCE(SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END), 0) as resolved_total
        ", [$now, $now, $now])->first() ?? (object) [
            'total' => 0,
            'open_total' => 0,
            'unassigned' => 0,
            'critical_open' => 0,
            'breached' => 0,
            'approaching' => 0,
            'resolved_total' => 0,
        ];

        $recentTickets = Ticket::with(['customer:id,name', 'category:id,name', 'assignedAgent:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        $agentsCount = Agent::count();
        $categoriesCount = Category::count();

        return compact('stats', 'recentTickets', 'agentsCount', 'categoriesCount');
    }
}
