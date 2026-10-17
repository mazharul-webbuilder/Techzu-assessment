<?php

namespace App\Services\Common;

use App\Enums\Priority;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Service calculating business-hours compliant SLA deadlines.
 *
 * Resolves both due date and early warning thresholds based on configured
 * business hours, working days, and priority rules.
 */
class SlaService
{
    public function __construct(
        protected BusinessHoursCalculator $calculator
    ) {}

    /**
     * Compute both resolution and warning deadlines for a priority starting from a given time.
     *
     * @param Priority $priority Target priority level
     * @param CarbonInterface $from Base start time
     * @return array{sla_due_at: CarbonImmutable, 'sla_warning_at': CarbonImmutable}
     */
    public function deadlinesFor(Priority $priority, CarbonInterface $from): array
    {
        $minutes = config("helpdesk.sla_minutes.{$priority->value}", $priority->slaMinutes());
        $threshold = config('helpdesk.warning_threshold', 0.8);
        $warningMinutes = (int) floor($minutes * $threshold);

        return [
            'sla_due_at' => $this->calculator->addBusinessMinutes($from, $minutes),
            'sla_warning_at' => $this->calculator->addBusinessMinutes($from, $warningMinutes),
        ];
    }

    /**
     * Calculate resolution due time and warning deadline (backwards-compatible helper).
     *
     * @param Priority $priority Target priority level
     * @param CarbonInterface|null $from Base timestamp (defaults to current time)
     * @return array{sla_due_at: CarbonImmutable, 'sla_warning_at': CarbonImmutable}
     */
    public function calculateDeadlines(Priority $priority, ?CarbonInterface $from = null): array
    {
        $base = $from ?? CarbonImmutable::now();

        return $this->deadlinesFor($priority, $base);
    }
}
