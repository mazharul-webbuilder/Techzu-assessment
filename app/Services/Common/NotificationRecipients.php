<?php

namespace App\Services\Common;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Helper resolving recipients for helpdesk notifications according to Section 9 matrix:
 *
 * - Ticket created: Customer (confirmation)
 * - Ticket assigned: The assigned agent
 * - Agent replied (public): Customer
 * - Customer replied: Assigned agent (all admins if unassigned)
 * - Ticket resolved: Customer
 * - SLA approaching: Assigned agent (all admins if unassigned)
 * - SLA breached: Assigned agent + all admins
 */
class NotificationRecipients
{
    /**
     * Resolve recipient for ticket creation confirmation.
     */
    public static function forTicketCreated(Ticket $ticket): Customer
    {
        return $ticket->customer;
    }

    /**
     * Resolve recipient when a ticket is assigned.
     */
    public static function forTicketAssigned(Ticket $ticket): ?Agent
    {
        return $ticket->assignedAgent;
    }

    /**
     * Resolve recipient when an agent posts a public reply.
     */
    public static function forAgentReplied(Ticket $ticket): Customer
    {
        return $ticket->customer;
    }

    /**
     * Resolve recipients when a customer posts a reply.
     * Returns assigned agent, or all admins if unassigned.
     *
     * @return Agent|Collection<int, Admin>
     */
    public static function forCustomerReplied(Ticket $ticket): Agent|Collection
    {
        return $ticket->assignedAgent ?? Admin::all();
    }

    /**
     * Resolve recipient when a ticket is resolved.
     */
    public static function forTicketResolved(Ticket $ticket): Customer
    {
        return $ticket->customer;
    }

    /**
     * Resolve recipients when SLA resolution target is approaching breach threshold.
     * Returns assigned agent, or all admins if unassigned.
     *
     * @return Agent|Collection<int, Admin>
     */
    public static function forSlaApproaching(Ticket $ticket): Agent|Collection
    {
        return $ticket->assignedAgent ?? Admin::all();
    }

    /**
     * Resolve recipients when SLA resolution deadline is breached.
     * Returns assigned agent + all admins.
     *
     * @return Collection<int, Agent|Admin>
     */
    public static function forSlaBreached(Ticket $ticket): Collection
    {
        $admins = Admin::all();

        if ($ticket->assignedAgent) {
            return collect([$ticket->assignedAgent])->concat($admins)->unique('email');
        }

        return $admins;
    }
}
