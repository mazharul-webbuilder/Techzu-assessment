<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Ticket;

/**
 * Service providing customer dashboard metrics and summary counts.
 */
class DashboardService
{
    /**
     * Get aggregate ticket counters for an authenticated customer using single conditional aggregation query.
     */
    public function getCustomerStats(Customer $customer): object
    {
        return Ticket::where('customer_id', $customer->id)
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END), 0) as open_count,
                COALESCE(SUM(CASE WHEN status = 'waiting_for_customer' THEN 1 ELSE 0 END), 0) as waiting_count,
                COALESCE(SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END), 0) as resolved_count
            ")
            ->first() ?? (object) [
                'total' => 0,
                'open_count' => 0,
                'waiting_count' => 0,
                'resolved_count' => 0,
            ];
    }
}
