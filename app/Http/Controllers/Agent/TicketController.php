<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Common\TicketAssignmentService;
use App\Services\Common\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected TicketAssignmentService $assignmentService
    ) {}

    /**
     * Display a listing of tickets visible to the authenticated agent.
     * Uses Ticket::visibleTo() to restrict to assigned or unassigned pool tickets.
     */
    public function index(\App\Http\Requests\TicketIndexRequest $request): View
    {
        $agent = auth('agent')->user();
        $tab = $request->validated('tab', 'all') ?: 'all';

        $tickets = $this->ticketService->getTicketsForAgent($agent, $tab, $request->validated());
        $counts = $this->ticketService->getAgentQueueCounts($agent);
        $categories = $this->ticketService->getActiveCategories();

        return view('agent.tickets.index', compact('tickets', 'tab', 'counts', 'categories'));
    }

    /**
     * Display ticket details, visible replies, and activity history.
     */
    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $agent = auth('agent')->user();
        $ticket = $this->ticketService->getTicketDetails($ticket, $agent);

        return view('agent.tickets.show', compact('ticket'));
    }

    /**
     * Claim an unassigned ticket from the queue.
     * Protected by atomic conditional UPDATE to serialize race conditions.
     */
    public function claim(Ticket $ticket): RedirectResponse
    {
        $this->authorize('claim', $ticket);

        $agent = auth('agent')->user();
        $this->assignmentService->claim($ticket, $agent);

        return back()->with('success', "Ticket {$ticket->ticket_number} successfully claimed! It is now in your active workstation.");
    }

    /**
     * Post a public reply or internal staff note to the ticket.
     */
    public function reply(\App\Http\Requests\StoreReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $internal = $request->isInternal();

        if ($internal) {
            $this->authorize('addNote', $ticket);
        } else {
            $this->authorize('reply', $ticket);
        }

        $agent = $request->user('agent');
        $this->ticketService->reply(
            ticket: $ticket,
            author: $agent,
            body: $request->validated('body'),
            internal: $internal,
            attachments: $request->file('attachments', [])
        );

        $msg = $internal ? 'Internal staff note recorded.' : 'Public reply posted to customer.';
        return back()->with('success', $msg);
    }

    /**
     * Change the ticket status according to state machine rules.
     */
    public function changeStatus(\App\Http\Requests\ChangeStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('changeStatus', $ticket);

        $agent = auth('agent')->user();
        $this->ticketService->changeStatus($ticket, $request->statusEnum(), $agent);

        return back()->with('success', "Ticket status updated to {$request->statusEnum()->label()}.");
    }

    /**
     * Change ticket priority and recalculate SLA.
     */
    public function changePriority(\App\Http\Requests\ChangePriorityRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('changePriority', $ticket);

        $agent = auth('agent')->user();
        $this->ticketService->changePriority($ticket, $request->priorityEnum(), $agent);

        return back()->with('success', "Priority updated to {$request->priorityEnum()->label()}. SLA targets recalculated.");
    }
}
