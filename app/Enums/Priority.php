<?php

namespace App\Enums;

/**
 * Represents the urgency/priority of a support ticket.
 *
 * Each priority determines the target resolution SLA deadline
 * defined in config/helpdesk.php.
 */
enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * All priority enum string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human-readable label for UI badges and filters.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /**
     * Target resolution SLA in minutes, dynamically read from config/helpdesk.php.
     *
     * Default fallbacks (if config is missing):
     * - Low: 2880 mins (48 hours)
     * - Medium: 1440 mins (24 hours)
     * - High: 480 mins (8 hours)
     * - Critical: 120 mins (2 hours)
     */
    public function slaMinutes(): int
    {
        $defaults = [
            self::Low->value => 2880,
            self::Medium->value => 1440,
            self::High->value => 480,
            self::Critical->value => 120,
        ];

        return (int) config("helpdesk.sla_minutes.{$this->value}", $defaults[$this->value]);
    }
}
