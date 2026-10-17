<?php

namespace App\Services\Common;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Service calculating exact business-hours aware timestamps.
 *
 * Implements the minute consumption algorithm from Section 8.2 of the architecture specification:
 * - Operates in the configured local business timezone (e.g. Asia/Dhaka)
 * - Skips non-working days (e.g. Friday and Saturday in BD work week)
 * - Skips configured holiday dates
 * - Advances outside-hours timestamps to the next working window opening
 * - Converts the final computed deadline back to UTC for database persistence
 */
class BusinessHoursCalculator
{
    protected string $tz;
    /** @var list<int> */
    protected array $days;
    protected string $start;
    protected string $end;
    /** @var list<string> */
    protected array $holidays;

    /**
     * @param string|null $tz Local timezone string
     * @param list<int>|null $days Carbon dayOfWeek integers (0=Sunday ... 6=Saturday)
     * @param string|null $start Daily opening time (HH:MM)
     * @param string|null $end Daily closing time (HH:MM)
     * @param list<string>|null $holidays List of Y-m-d holiday strings
     */
    public function __construct(
        ?string $tz = null,
        ?array $days = null,
        ?string $start = null,
        ?string $end = null,
        ?array $holidays = null
    ) {
        $this->tz = $tz ?? config('helpdesk.business_hours.timezone', 'Asia/Dhaka');
        $this->days = $days ?? config('helpdesk.business_hours.days', [0, 1, 2, 3, 4]);
        $this->start = $start ?? config('helpdesk.business_hours.start', '09:00');
        $this->end = $end ?? config('helpdesk.business_hours.end', '18:00');
        $this->holidays = $holidays ?? config('helpdesk.business_hours.holidays', []);
    }

    /**
     * Add N business minutes to a starting timestamp.
     *
     * Walks forward through working windows, consuming minutes.
     *
     * @param CarbonInterface $start Starting timestamp (any timezone)
     * @param int $minutes Business minutes to add
     * @return CarbonImmutable Resulting deadline in UTC
     */
    public function addBusinessMinutes(CarbonInterface $start, int $minutes): CarbonImmutable
    {
        $c = CarbonImmutable::instance($start)->setTimezone($this->tz);

        while (true) {
            $c = $this->moveIntoBusinessTime($c);
            $close = $c->setTimeFromTimeString($this->end);

            // Timestamps ensure consistency across all Carbon versions
            $available = intdiv($close->getTimestamp() - $c->getTimestamp(), 60);

            if ($minutes <= $available) {
                return $c->addMinutes($minutes)->utc();
            }

            $minutes -= $available;
            $c = $this->nextBusinessDayStart($c);
        }
    }

    /**
     * Check if a given date is a configured business day and not a holiday.
     */
    public function isBusinessDay(CarbonImmutable $c): bool
    {
        return in_array($c->dayOfWeek, $this->days, true)
            && !in_array($c->format('Y-m-d'), $this->holidays, true);
    }

    /**
     * Move the cursor forward into valid business hours.
     *
     * - Not a business day -> Next business day at opening time
     * - Before opening -> Today at opening time
     * - At or after closing -> Next business day at opening time
     * - Otherwise -> Cursor is already inside working window
     */
    public function moveIntoBusinessTime(CarbonImmutable $c): CarbonImmutable
    {
        if (!$this->isBusinessDay($c)) {
            return $this->nextBusinessDayStart($c);
        }

        $open = $c->setTimeFromTimeString($this->start);
        if ($c->lt($open)) {
            return $open;
        }

        $close = $c->setTimeFromTimeString($this->end);
        if ($c->gte($close)) {
            return $this->nextBusinessDayStart($c);
        }

        return $c;
    }

    /**
     * Advance cursor to the opening time of the next available business day.
     */
    public function nextBusinessDayStart(CarbonImmutable $c): CarbonImmutable
    {
        do {
            $c = $c->addDay()->setTimeFromTimeString($this->start);
        } while (!$this->isBusinessDay($c));

        return $c;
    }

    /**
     * Get the configured business timezone.
     */
    public function getTimezone(): string
    {
        return $this->tz;
    }
}
