<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Common\TicketService;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * Display a listing of all tickets across the system for administrator.
     */
    public function index(): View
    {
        $admin = auth('admin')->user();
        $tickets = $this->ticketService->getTicketsForActor($admin);

        return view('admin.tickets.index', compact('tickets'));
    }

    /**
     * Display ticket details, visible replies, and activity history.
     */
    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $admin = auth('admin')->user();
        $ticket = $this->ticketService->getTicketDetails($ticket, $admin);

        return view('admin.tickets.show', compact('ticket'));
    }
}
