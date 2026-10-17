<?php

namespace App\Http\Controllers\Customer;

use App\Enums\Priority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreTicketRequest;
use App\Models\Ticket;
use App\Services\Customer\DashboardService;
use App\Services\Customer\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected DashboardService $dashboardService
    ) {}

    /**
     * Display a listing of tickets created by the authenticated customer.
     */
    public function index(\App\Http\Requests\TicketIndexRequest $request): View
    {
        $customer = auth('customer')->user();

        $tickets = $this->ticketService->getTicketsForCustomer($customer, $request->validated());
        $stats = $this->dashboardService->getCustomerStats($customer);
        $categories = $this->ticketService->getActiveCategories();

        return view('customer.tickets.index', compact('tickets', 'stats', 'categories'));
    }

    /**
     * Show the ticket submission form with active categories.
     */
    public function create(): View
    {
        $categories = $this->ticketService->getActiveCategories();
        $priorities = Priority::cases();

        return view('customer.tickets.create', compact('categories', 'priorities'));
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $ticket = $this->ticketService->create($customer, $request->validated());

        return redirect()
            ->route('customer.tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} was successfully submitted.");
    }

    /**
     * Display the specified ticket details and activity timeline.
     */
    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $customer = auth('customer')->user();
        $ticket = $this->ticketService->getTicketDetails($ticket, $customer);

        return view('customer.tickets.show', compact('ticket'));
    }

    /**
     * Post a public reply to the ticket discussion.
     */
    public function reply(\App\Http\Requests\StoreReplyRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $customer = $request->user('customer');
        $this->ticketService->reply(
            ticket: $ticket,
            author: $customer,
            body: $request->validated('body'),
            internal: false, // Customers can NEVER post internal notes
            attachments: $request->file('attachments', [])
        );

        return back()->with('success', 'Your reply has been posted successfully.');
    }

    /**
     * Customer confirms resolution and closes the resolved ticket.
     */
    public function close(Ticket $ticket): RedirectResponse
    {
        $this->authorize('changeStatus', $ticket);

        $customer = auth('customer')->user();
        $this->ticketService->close($ticket, $customer);

        return back()->with('success', 'Ticket has been marked as Closed. Thank you for your feedback!');
    }
}
