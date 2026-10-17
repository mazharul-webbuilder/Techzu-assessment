<?php

namespace App\Enums;

/**
 * Represents the current SLA compliance state of an active ticket.
 *
 * Calculated by comparing current time with stored sla_warning_at and sla_due_at deadlines:
 * - Within: now < sla_warning_at
 * - Approaching: sla_warning_at <= now < sla_due_at
 * - Breached: now >= sla_due_at
 */
enum SlaStatus: string
{
    case Within = 'within';
    case Approaching = 'approaching';
    case Breached = 'breached';

    /**
     * Human-readable label for UI display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Within => 'Within SLA',
            self::Approaching => 'Approaching Breach',
            self::Breached => 'SLA Breached',
        };
    }

    /**
     * Bootstrap 5 badge CSS classes for standard styling across portals.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Within => 'bg-success',
            self::Approaching => 'bg-warning text-dark',
            self::Breached => 'bg-danger',
        };
    }
}
