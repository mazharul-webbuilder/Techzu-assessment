<?php

namespace App\Services\Common;

use App\Enums\ActivityEvent;
use App\Models\Ticket;
use App\Models\TicketActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Service writing append-only audit activity records for ticket events.
 *
 * MUST be invoked inside the caller's DB::transaction to ensure all state changes
 * commit or rollback atomically with their audit logs.
 */
class ActivityLogger
{
    /**
     * Record an immutable ticket activity event.
     *
     * @param Ticket $ticket Target ticket instance
     * @param ActivityEvent $event Lifecycle event enum
     * @param Model|null $actor The user triggering the action (null indicates automated system)
     * @param array<string, mixed> $old Previous state snapshot
     * @param array<string, mixed> $new New state snapshot
     * @param string|null $description Human-readable audit narrative
     */
    public function log(
        Ticket $ticket,
        ActivityEvent $event,
        ?Model $actor,
        array $old = [],
        array $new = [],
        ?string $description = null
    ): TicketActivity {
        return TicketActivity::create([
            'ticket_id' => $ticket->id,
            'actor_type' => $actor ? $actor->getMorphClass() : null,
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'old_value' => !empty($old) ? $old : null,
            'new_value' => !empty($new) ? $new : null,
            'description' => $description,
        ]);
    }
}
