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
    public function index(): View
    {
        $customer = auth('customer')->user();

        $tickets = $this->ticketService->getTicketsForCustomer($customer);
        $stats = $this->dashboardService->getCustomerStats($customer);

        return view('customer.tickets.index', compact('tickets', 'stats'));
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
}
