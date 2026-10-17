<?php

namespace App\Enums;

/**
 * Identifies the type of audit event recorded in the ticket_activities table.
 *
 * Each activity entry documents who performed what modification and when,
 * preserving a full, immutable audit trail for every ticket.
 */
enum ActivityEvent: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';
    case StatusChanged = 'status_changed';
    case PriorityChanged = 'priority_changed';
    case Replied = 'replied';
    case InternalNoteAdded = 'internal_note_added';
    case Escalated = 'escalated';

    /**
     * Human-readable description for timeline presentation.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Ticket Created',
            self::Assigned => 'Assigned to Agent',
            self::Reassigned => 'Reassigned Agent',
            self::StatusChanged => 'Status Changed',
            self::PriorityChanged => 'Priority Changed',
            self::Replied => 'Reply Added',
            self::InternalNoteAdded => 'Internal Note Added',
            self::Escalated => 'Ticket Escalated',
        };
    }
}
