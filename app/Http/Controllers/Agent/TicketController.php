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
    public function index(): View
    {
        $agent = auth('agent')->user();
        $tickets = $this->ticketService->getTicketsForActor($agent);

        return view('agent.tickets.index', compact('tickets'));
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
}
