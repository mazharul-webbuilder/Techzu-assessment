<?php

namespace App\Services\Common;

use App\Enums\Priority;
use Illuminate\Support\Carbon;

/**
 * Service calculating initial and rescheduled SLA target deadlines.
 *
 * Currently implements temporary linear minute calculation for Phase 5.
 * Replaced with business-hours aware schedule calculator in Phase 9.
 */
class SlaService
{
    /**
     * Calculate resolution due time and early-warning threshold for a given priority.
     *
     * @param Priority $priority Target priority level
     * @param Carbon|null $from Base timestamp (defaults to current time)
     * @return array{sla_due_at: Carbon, sla_warning_at: Carbon}
     */
    public function calculateDeadlines(Priority $priority, ?Carbon $from = null): array
    {
        $base = ($from ?? Carbon::now())->copy();
        $totalMinutes = $priority->slaMinutes();

        // Warning threshold is evaluated at 80% of total elapsed SLA time
        $warningMinutes = (int) round($totalMinutes * 0.8);

        return [
            'sla_due_at' => $base->copy()->addMinutes($totalMinutes),
            'sla_warning_at' => $base->copy()->addMinutes($warningMinutes),
        ];
    }
}
