<?php

namespace App\Enums;

/**
 * Represents the lifecycle status of a support ticket.
 *
 * Implements strict state-machine validation via canTransitionTo()
 * to prevent illegal lifecycle jumps (e.g., from closed back to open).
 */
enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForCustomer = 'waiting_for_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Human-readable label for display in UI portals.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::WaitingForCustomer => 'Waiting for Customer',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    /**
     * All status enum string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Active ticket statuses that require ongoing attention and SLA monitoring.
     * Resolved and closed tickets are considered inactive/completed.
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [
            self::Open->value,
            self::InProgress->value,
            self::WaitingForCustomer->value,
        ];
    }

    /**
     * Validates whether a state transition from the current status to a target status is permitted.
     *
     * Allowed State Flow:
     * - Open                 -> In Progress, Waiting for Customer, Resolved
     * - In Progress          -> Waiting for Customer, Resolved
     * - Waiting for Customer -> In Progress, Resolved
     * - Resolved             -> Closed, In Progress (Reopen)
     * - Closed               -> Terminal state (no further transitions allowed)
     */
    public function canTransitionTo(self $to): bool
    {
        // Transitioning to the exact same status is a no-op / allowed
        if ($this === $to) {
            return true;
        }

        $transitions = [
            self::Open->value => [
                self::InProgress,
                self::WaitingForCustomer,
                self::Resolved,
            ],
            self::InProgress->value => [
                self::WaitingForCustomer,
                self::Resolved,
            ],
            self::WaitingForCustomer->value => [
                self::InProgress,
                self::Resolved,
            ],
            self::Resolved->value => [
                self::Closed,
                self::InProgress, // Reopen path
            ],
            self::Closed->value => [], // Closed is terminal
        ];

        return in_array($to, $transitions[$this->value] ?? [], true);
    }
}
