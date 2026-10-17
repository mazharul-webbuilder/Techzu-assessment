<div class="table-responsive">
    <table class="table table-modern align-middle mb-0">
        <thead>
            <tr>
                <th class="ps-4">Ticket Number</th>
                <th>Subject &amp; Category</th>
                @if($portal !== 'customer')
                    <th>Customer</th>
                @endif
                <th>Priority</th>
                <th>Status</th>
                <th>SLA Target</th>
                @if($portal !== 'agent')
                    <th>Assigned Agent</th>
                @endif
                <th>Created</th>
                <th class="text-end pe-4">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $ticket)
                @php
                    $isBreached = $ticket->sla_due_at && $ticket->sla_due_at->isPast() && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
                    $isApproaching = $ticket->sla_warning_at && $ticket->sla_warning_at->isPast() && !$isBreached && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
                    $showRoute = match($portal) {
                        'agent' => route('agent.tickets.show', $ticket),
                        'admin' => route('admin.tickets.show', $ticket),
                        default => route('customer.tickets.show', $ticket),
                    };
                @endphp
                <tr>
                    <!-- Ticket Number -->
                    <td class="ps-4">
                        <a href="{{ $showRoute }}" class="fw-bold text-primary text-decoration-none font-monospace small">
                            {{ $ticket->ticket_number }}
                        </a>
                    </td>

                    <!-- Subject & Category -->
                    <td>
                        <a href="{{ $showRoute }}" class="text-decoration-none text-dark fw-semibold d-block mb-1 text-truncate" style="max-width: 280px;">
                            {{ $ticket->subject }}
                        </a>
                        <span class="badge bg-light text-secondary border small fw-normal">
                            <i class="bi bi-tag me-1"></i>{{ $ticket->category?->name ?? 'General' }}
                        </span>
                    </td>

                    <!-- Customer (if staff) -->
                    @if($portal !== 'customer')
                        <td class="small">
                            <div class="fw-semibold text-dark">{{ $ticket->customer?->name ?? 'Guest' }}</div>
                            <span class="text-muted" style="font-size: 0.72rem;">{{ $ticket->customer?->email }}</span>
                        </td>
                    @endif

                    <!-- Priority Badge -->
                    <td>
                        @include('tickets._badge_priority', ['priority' => $ticket->priority])
                    </td>

                    <!-- Status Badge -->
                    <td>
                        @include('tickets._badge_status', ['status' => $ticket->status])
                    </td>

                    <!-- SLA Target -->
                    <td>
                        @if($ticket->sla_due_at)
                            @if(in_array($ticket->status->value, ['resolved', 'closed']))
                                <span class="text-success small fw-medium">
                                    <i class="bi bi-check-circle me-1"></i>Resolved
                                </span>
                            @elseif($isBreached)
                                <span class="badge badge-soft-danger rounded-pill px-2 py-1">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Breached
                                </span>
                            @elseif($isApproaching)
                                <span class="badge badge-soft-warning rounded-pill px-2 py-1">
                                    <i class="bi bi-hourglass-split me-1"></i>Warning
                                </span>
                            @else
                                <div class="small fw-medium text-dark d-flex align-items-center gap-1">
                                    <i class="bi bi-clock text-primary"></i>
                                    <span>{{ $ticket->sla_due_at->diffForHumans() }}</span>
                                </div>
                            @endif
                        @else
                            <span class="text-muted small">&mdash;</span>
                        @endif
                    </td>

                    <!-- Assigned Agent (for customer/admin) -->
                    @if($portal !== 'agent')
                        <td class="small">
                            @if($ticket->assignedAgent)
                                <span class="text-dark fw-medium">{{ $ticket->assignedAgent->name }}</span>
                            @else
                                <span class="badge badge-soft-secondary rounded-pill px-2 py-1">Unassigned</span>
                            @endif
                        </td>
                    @endif

                    <!-- Created Date -->
                    <td>
                        <div class="small text-dark fw-medium">{{ $ticket->created_at->format('M d, Y') }}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ $ticket->created_at->format('h:i A') }}</div>
                    </td>

                    <!-- Action -->
                    <td class="text-end pe-4">
                        <div class="d-inline-flex align-items-center gap-2">
                            @if($portal === 'agent' && $ticket->assigned_agent_id === null)
                                <form method="POST" action="{{ route('agent.tickets.claim', $ticket) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary fw-semibold px-2 py-1 rounded-2 shadow-none" style="font-size: 0.78rem;">
                                        <i class="bi bi-hand-index-thumb me-1"></i>Claim
                                    </button>
                                </form>
                            @endif
                            <a href="{{ $showRoute }}" class="btn btn-sm btn-light border text-secondary fw-semibold px-3 rounded-3 shadow-none">
                                <span>Details</span>
                                <i class="bi bi-chevron-right ms-1 text-muted" style="font-size: 0.75rem;"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $portal === 'agent' ? 8 : 9 }}" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                        <p class="small mb-0">No tickets found in this queue.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($tickets->hasPages())
    <div class="p-3 px-4 border-top bg-light d-flex justify-content-end">
        {{ $tickets->links() }}
    </div>
@endif
