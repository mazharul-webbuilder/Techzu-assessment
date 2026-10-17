<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization policy governing single-record ticket operations.
 *
 * Implements the security matrix from Section 7.1 of the architecture specification.
 * Evaluates actor role via instanceof check across Customer, Agent, and Admin guards.
 */
class TicketPolicy
{
    /**
     * Determine whether the authenticated actor can view the ticket.
     *
     * - Customer: Own tickets only.
     * - Agent: Assigned tickets OR unassigned pool tickets (for triage/claiming).
     * - Admin: All tickets across the system.
     */
    public function view(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user instanceof Customer) {
            return $ticket->customer_id === $user->id;
        }

        if ($user instanceof Agent) {
            return $ticket->assigned_agent_id === $user->id || $ticket->assigned_agent_id === null;
        }

        if ($user instanceof Admin) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the actor can edit ticket subject or description.
     *
     * - Customer: Own tickets only, and ONLY while still in 'open' status.
     * - Agent: Not permitted to rewrite customer inquiry subject/description.
     * - Admin: Allowed.
     */
    public function update(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user instanceof Customer) {
            return $ticket->customer_id === $user->id && $ticket->status === TicketStatus::Open;
        }

        if ($user instanceof Agent) {
            return false;
        }

        if ($user instanceof Admin) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the actor can post a public reply.
     *
     * - Customer: Own tickets, provided the ticket is not closed.
     * - Agent: Assigned tickets only.
     * - Admin: Allowed.
     */
    public function reply(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user instanceof Customer) {
            return $ticket->customer_id === $user->id && $ticket->status !== TicketStatus::Closed;
        }

        if ($user instanceof Agent) {
            return $ticket->assigned_agent_id === $user->id;
        }

        if ($user instanceof Admin) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the actor can add an internal staff note.
     *
     * - Customer: NEVER allowed (strictly staff-only).
     * - Agent: Assigned tickets only.
     * - Admin: Allowed.
     */
    public function addNote(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user instanceof Customer) {
            return false;
        }

        if ($user instanceof Agent) {
            return $ticket->assigned_agent_id === $user->id;
        }

        if ($user instanceof Admin) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the actor can claim an unassigned ticket.
     *
     * - Customer: Not permitted.
     * - Agent: Allowed if the ticket is unassigned and active.
     * - Admin: Not applicable (admins assign rather than claim).
     */
    public function claim(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user instanceof Agent) {
            return $ticket->assigned_agent_id === null && in_array($ticket->status, [TicketStatus::Open, TicketStatus::InProgress]);
        }

        return false;
    }

    /**
     * Determine whether the actor can manually or automatically assign the ticket.
     *
     * - Customer: Not permitted.
     * - Agent: Not permitted.
     * - Admin: Allowed.
     */
    public function assign(Authenticatable $user, Ticket $ticket): bool
    {
        return $user instanceof Admin;
    }

    /**
     * Determine whether the actor can change the ticket status.
     *
     * - Customer: Can only close their own ticket after it has been marked 'resolved'.
     * - Agent: Assigned tickets only (must follow valid state machine transitions).
     * - Admin: Allowed.
     */
    public function changeStatus(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user instanceof Customer) {
            return $ticket->customer_id === $user->id && $ticket->status === TicketStatus::Resolved;
        }

        if ($user instanceof Agent) {
            return $ticket->assigned_agent_id === $user->id;
        }

        if ($user instanceof Admin) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the actor can change ticket priority or category.
     *
     * - Customer: Not permitted after creation.
     * - Agent: Assigned tickets only.
     * - Admin: Allowed.
     */
    public function changePriority(Authenticatable $user, Ticket $ticket): bool
    {
        if ($user instanceof Agent) {
            return $ticket->assigned_agent_id === $user->id;
        }

        if ($user instanceof Admin) {
            return true;
        }

        return false;
    }
}
