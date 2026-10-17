<?php

namespace App\Services\Agent;

use App\Models\Agent;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Service managing agent personal workload metrics and queue statistics.
 */
class DashboardService
{
    /**
     * Get personal workload stats, queue counters, and assigned tickets for an agent.
     *
     * @return array{stats: object, unassignedCount: int, myTickets: \Illuminate\Database\Eloquent\Collection}
     */
    public function getAgentDashboardData(Agent $agent): array
    {
        $now = Carbon::now();

        $stats = Ticket::where('assigned_agent_id', $agent->id)
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as active_count,
                COALESCE(SUM(CASE WHEN sla_due_at <= ? AND status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as breached_count,
                COALESCE(SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? AND status IN ('open', 'in_progress', 'waiting_for_customer') THEN 1 ELSE 0 END), 0) as approaching_count,
                COALESCE(SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END), 0) as resolved_count
            ", [$now, $now, $now])->first() ?? (object) [
                'total' => 0,
                'active_count' => 0,
                'breached_count' => 0,
                'approaching_count' => 0,
                'resolved_count' => 0,
            ];

        $unassignedCount = Ticket::whereNull('assigned_agent_id')
            ->whereIn('status', ['open', 'in_progress', 'waiting_for_customer'])
            ->count();

        $myTickets = Ticket::where('assigned_agent_id', $agent->id)
            ->with(['customer:id,name', 'category:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        return compact('stats', 'unassignedCount', 'myTickets');
    }
}
