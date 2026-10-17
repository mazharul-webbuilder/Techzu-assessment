<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignTicketRequest;
use App\Models\Agent;
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
     * Display a listing of all tickets across the system for administrator.
     */
    public function index(\App\Http\Requests\TicketIndexRequest $request): View
    {
        $admin = auth('admin')->user();
        $tickets = $this->ticketService->getTicketsForActor($admin, $request->validated());
        $categories = $this->ticketService->getActiveCategories();
        $agents = $this->assignmentService->getAgentsWithWorkload();

        return view('admin.tickets.index', compact('tickets', 'categories', 'agents'));
    }

    /**
     * Display ticket details, visible replies, activity history, and available agents for assignment.
     */
    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $admin = auth('admin')->user();
        $ticket = $this->ticketService->getTicketDetails($ticket, $admin);
        $agents = $this->assignmentService->getAgentsWithWorkload();

        return view('admin.tickets.show', compact('ticket', 'agents'));
    }

    /**
     * Manually assign or reassign a ticket to a specific support agent.
     */
    public function assign(AssignTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);

        $admin = auth('admin')->user();
        $agent = Agent::findOrFail($request->validated('agent_id'));

        $this->assignmentService->assign($ticket, $agent, $admin);

        return back()->with('success', "Ticket {$ticket->ticket_number} successfully assigned to {$agent->name}.");
    }

    /**
     * Automatically assign the ticket using the least-loaded available agent strategy.
     */
    public function autoAssign(Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);

        $admin = auth('admin')->user();
        $assigned = $this->assignmentService->autoAssign($ticket, $admin);

        if ($assigned) {
            $ticket->refresh();
            return back()->with('success', "Ticket {$ticket->ticket_number} automatically assigned to {$ticket->assignedAgent->name}.");
        }

        return back()->with('error', 'Auto-assignment could not complete: No available agents in pool or ticket already assigned.');
    }

    /**
     * Post a public reply or internal staff note to the ticket discussion.
     */
    public function reply(\App\Http\Requests\StoreReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $internal = $request->isInternal();

        if ($internal) {
            $this->authorize('addNote', $ticket);
        } else {
            $this->authorize('reply', $ticket);
        }

        $admin = $request->user('admin');
        $this->ticketService->reply(
            ticket: $ticket,
            author: $admin,
            body: $request->validated('body'),
            internal: $internal,
            attachments: $request->file('attachments', [])
        );

        $msg = $internal ? 'Internal staff note recorded.' : 'Public reply posted to customer.';
        return back()->with('success', $msg);
    }

    /**
     * Change ticket status according to state machine rules.
     */
    public function changeStatus(\App\Http\Requests\ChangeStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('changeStatus', $ticket);

        $admin = auth('admin')->user();
        $this->ticketService->changeStatus($ticket, $request->statusEnum(), $admin);

        return back()->with('success', "Ticket status updated to {$request->statusEnum()->label()}.");
    }

    /**
     * Change ticket priority and recalculate SLA targets.
     */
    public function changePriority(\App\Http\Requests\ChangePriorityRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('changePriority', $ticket);

        $admin = auth('admin')->user();
        $this->ticketService->changePriority($ticket, $request->priorityEnum(), $admin);

        return back()->with('success', "Priority updated to {$request->priorityEnum()->label()}. SLA targets recalculated.");
    }
}
