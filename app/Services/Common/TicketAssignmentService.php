<?php

namespace App\Services\Common;

use App\Enums\ActivityEvent;
use App\Enums\TicketStatus;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service managing ticket assignments, atomic concurrent claiming,
 * and automated workload-based agent distribution.
 */
class TicketAssignmentService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {}

    /**
     * Agent claims an unassigned ticket.
     *
     * Concurrency Solution (Section 8.4):
     * Uses an atomic single-statement conditional update:
     * UPDATE tickets SET assigned_agent_id = ?, status = 'in_progress' WHERE id = ? AND assigned_agent_id IS NULL
     *
     * If two agents attempt to claim simultaneously, the DB takes a row lock.
     * The first one claims the row (1 affected row). The second one evaluates
     * the condition after lock release, finds assigned_agent_id is no longer NULL,
     * and updates 0 rows. This serializes the race with zero application-level locks.
     *
     * @throws TicketAlreadyAssignedException
     */
    public function claim(Ticket $ticket, Agent $agent): Ticket
    {
        $affected = Ticket::query()
            ->whereKey($ticket->id)
            ->whereNull('assigned_agent_id')
            ->update([
                'assigned_agent_id' => $agent->id,
                'status' => TicketStatus::InProgress,
            ]);

        if ($affected === 0) {
            throw new TicketAlreadyAssignedException();
        }

        $ticket->refresh();

        $this->activityLogger->log(
            ticket: $ticket,
            event: ActivityEvent::Assigned,
            actor: $agent,
            old: ['assigned_agent_id' => null, 'status' => TicketStatus::Open->value],
            new: ['assigned_agent_id' => $agent->id, 'status' => TicketStatus::InProgress->value],
            description: "Ticket claimed by agent {$agent->name}"
        );

        return $ticket;
    }

    /**
     * Admin manually assigns or reassigns a ticket to a specific support agent.
     */
    public function assign(Ticket $ticket, Agent $agent, Admin $by): Ticket
    {
        return DB::transaction(function () use ($ticket, $agent, $by) {
            // Lock row with exclusive lock to prevent concurrent races
            $lockedTicket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $oldAgentId = $lockedTicket->assigned_agent_id;
            $oldStatus = $lockedTicket->status;
            $isReassignment = !is_null($oldAgentId);

            // If ticket was untouched in 'open' status, transition it to 'in_progress'
            $newStatus = ($lockedTicket->status === TicketStatus::Open)
                ? TicketStatus::InProgress
                : $lockedTicket->status;

            $lockedTicket->update([
                'assigned_agent_id' => $agent->id,
                'status' => $newStatus,
            ]);

            $this->activityLogger->log(
                ticket: $lockedTicket,
                event: $isReassignment ? ActivityEvent::Reassigned : ActivityEvent::Assigned,
                actor: $by,
                old: ['assigned_agent_id' => $oldAgentId, 'status' => $oldStatus->value],
                new: ['assigned_agent_id' => $agent->id, 'status' => $newStatus->value],
                description: $isReassignment
                    ? "Ticket reassigned to agent {$agent->name} by {$by->name}"
                    : "Ticket assigned to agent {$agent->name} by {$by->name}"
            );

            return $lockedTicket;
        });
    }

    /**
     * Select the optimal agent based on current active workload.
     *
     * Strategy:
     * - Only considers available agents (is_available = true)
     * - Evaluates active tickets (Open, In Progress, Waiting for Customer)
     * - Selects the agent with the fewest active tickets
     * - Stable tie-breaker by agent ID
     */
    public function pickAgent(): ?Agent
    {
        return Agent::query()
            ->where('is_available', true)
            ->withCount(['tickets as open_tickets_count' => function ($q) {
                $q->whereIn('status', TicketStatus::activeValues());
            }])
            ->orderBy('open_tickets_count')
            ->orderBy('id')
            ->first();
    }

    /**
     * Automatically assign an unassigned ticket using the workload strategy.
     *
     * Uses the same atomic conditional UPDATE as claim() so race conditions
     * during concurrent ticket creation never double-assign a ticket.
     *
     * @param Ticket $ticket Target unassigned ticket
     * @param Admin|null $by Admin if manually triggered, null for automated system/job
     * @return bool True if successfully assigned, false if no agent available or already assigned
     */
    public function autoAssign(Ticket $ticket, ?Admin $by = null): bool
    {
        $agent = $this->pickAgent();

        if (!$agent) {
            $this->activityLogger->log(
                ticket: $ticket,
                event: ActivityEvent::Assigned,
                actor: $by,
                old: [],
                new: [],
                description: "Auto-assignment skipped: No available agents in pool."
            );
            return false;
        }

        // Concurrency guard: conditional update ensures atomicity
        $affected = Ticket::query()
            ->whereKey($ticket->id)
            ->whereNull('assigned_agent_id')
            ->update([
                'assigned_agent_id' => $agent->id,
                'status' => TicketStatus::InProgress,
            ]);

        if ($affected === 0) {
            // Already claimed or assigned by a concurrent process
            return false;
        }

        $ticket->refresh();

        $this->activityLogger->log(
            ticket: $ticket,
            event: ActivityEvent::Assigned,
            actor: $by,
            old: ['assigned_agent_id' => null, 'status' => TicketStatus::Open->value],
            new: ['assigned_agent_id' => $agent->id, 'status' => TicketStatus::InProgress->value],
            description: $by
                ? "Automatically assigned to {$agent->name} by {$by->name} (workload: {$agent->open_tickets_count} active tickets)"
                : "Automatically assigned to {$agent->name} (workload: {$agent->open_tickets_count} active tickets)"
        );

        return true;
    }

    /**
     * Get all active agents with their current active workload count.
     *
     * @return Collection<int, Agent>
     */
    public function getAgentsWithWorkload(): Collection
    {
        return Agent::query()
            ->withCount(['tickets as active_tickets_count' => function ($q) {
                $q->whereIn('status', TicketStatus::activeValues());
            }])
            ->orderBy('name')
            ->get();
    }
}
