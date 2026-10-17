@extends('layouts.app')

@section('title', 'My Support Tickets')
@section('portal_name', 'Customer Portal')
@section('user_name', Auth::guard('customer')->user()?->name ?? 'Customer')

@section('logout_button')
<form method="POST" action="{{ route('customer.logout') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-light px-3 py-1 rounded-pill" style="font-size: 0.82rem;">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
    </button>
</form>
@endsection

@section('content')
<!-- Page Header & Actions -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small text-muted mb-1">
                <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-house me-1"></i>Portal</a></li>
                <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">Support Tickets</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1 display-font text-dark">My Support Tickets</h2>
        <p class="text-muted small mb-0">Track real-time resolution status, response metrics, and conversation history.</p>
    </div>
    <div>
        <a href="{{ route('customer.tickets.create') }}" class="btn btn-portal-primary d-inline-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>Submit New Ticket</span>
        </a>
    </div>
</div>

<!-- KPI Summary Metric Cards -->
<div class="row g-3 mb-4">
    <!-- Total Tickets -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Total Submitted</span>
                <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5;">
                    <i class="bi bi-ticket-detailed"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-dark lh-1 mb-1">{{ $stats->total ?? $tickets->total() }}</div>
            <div class="text-muted small" style="font-size: 0.78rem;">All-time requests</div>
        </div>
    </div>

    <!-- Active / In Progress -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">In Progress</span>
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-warning lh-1 mb-1 d-flex align-items-center gap-2">
                <span>{{ $stats->open_count ?? 0 }}</span>
                @if(($stats->open_count ?? 0) > 0)
                    <span class="live-pulse"></span>
                @endif
            </div>
            <div class="text-muted small" style="font-size: 0.78rem;">Actively being worked on</div>
        </div>
    </div>

    <!-- Awaiting Action -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Awaiting You</span>
                <div class="stat-icon" style="background: rgba(2, 132, 199, 0.1); color: #0284c7;">
                    <i class="bi bi-chat-left-dots"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-info lh-1 mb-1">{{ $stats->waiting_count ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.78rem;">Requires your feedback</div>
        </div>
    </div>

    <!-- Resolved / Closed -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Resolved</span>
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-success lh-1 mb-1">{{ $stats->resolved_count ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.78rem;">Successfully completed</div>
        </div>
    </div>
</div>

<!-- Tickets Table Section -->
<div class="modern-card">
    <div class="p-3 px-4 border-bottom bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0 text-dark display-font fs-6">Recent Tickets</h5>
            <span class="badge badge-soft-primary rounded-pill px-2 py-1">{{ $tickets->total() }} total</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" placeholder="Filter tickets..." id="ticketSearchInput" onkeyup="filterTicketsTable()">
            </div>
        </div>
    </div>

    @if($tickets->isEmpty())
        <div class="text-center py-5 px-4">
            <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 72px; height: 72px; background: rgba(79, 70, 229, 0.08); color: #4f46e5; font-size: 2rem;">
                <i class="bi bi-inbox"></i>
            </div>
            <h5 class="fw-bold text-dark display-font">No tickets created yet</h5>
            <p class="text-muted small mb-4 mx-auto" style="max-width: 420px;">Whenever you need assistance, submit a ticket and our support team will respond within the guaranteed SLA window.</p>
            <a href="{{ route('customer.tickets.create') }}" class="btn btn-portal-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-plus-lg"></i>
                <span>Create Your First Ticket</span>
            </a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-modern align-middle" id="customerTicketsTable">
                <thead>
                    <tr>
                        <th class="ps-4">Ticket Number</th>
                        <th>Subject &amp; Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>SLA Due Target</th>
                        <th>Submitted</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tickets as $ticket)
                        @php
                            $statusBadge = match($ticket->status->value) {
                                'open' => 'badge-soft-primary',
                                'in_progress' => 'badge-soft-warning',
                                'waiting_for_customer' => 'badge-soft-info',
                                'resolved' => 'badge-soft-success',
                                'closed' => 'badge-soft-secondary',
                                default => 'badge-soft-secondary',
                            };

                            $priorityBadge = match($ticket->priority->value) {
                                'critical' => ['class' => 'badge-soft-danger', 'icon' => 'bi-lightning-charge-fill'],
                                'high' => ['class' => 'badge-soft-warning', 'icon' => 'bi-exclamation-circle-fill'],
                                'medium' => ['class' => 'badge-soft-info', 'icon' => 'bi-dash-circle-fill'],
                                'low' => ['class' => 'badge-soft-success', 'icon' => 'bi-info-circle-fill'],
                                default => ['class' => 'badge-soft-secondary', 'icon' => 'bi-flag-fill'],
                            };

                            $isBreached = $ticket->sla_due_at && $ticket->sla_due_at->isPast() && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
                        @endphp
                        <tr>
                            <!-- Ticket Number -->
                            <td class="ps-4">
                                <a href="{{ route('customer.tickets.show', $ticket) }}" class="fw-bold text-primary text-decoration-none d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.88rem;">
                                    <span>{{ $ticket->ticket_number }}</span>
                                </a>
                            </td>

                            <!-- Subject & Category -->
                            <td>
                                <div>
                                    <a href="{{ route('customer.tickets.show', $ticket) }}" class="text-decoration-none text-dark fw-semibold d-block mb-1 text-truncate" style="max-width: 320px;">
                                        {{ $ticket->subject }}
                                    </a>
                                    <span class="badge bg-light text-secondary border small fw-normal">
                                        <i class="bi bi-tag me-1"></i>{{ $ticket->category->name }}
                                    </span>
                                </div>
                            </td>

                            <!-- Priority -->
                            <td>
                                <span class="badge {{ $priorityBadge['class'] }} rounded-pill px-2 py-1">
                                    <i class="bi {{ $priorityBadge['icon'] }} me-1"></i>
                                    {{ $ticket->priority->label() }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="badge {{ $statusBadge }} rounded-pill px-2 py-1">
                                    {{ $ticket->status->label() }}
                                </span>
                            </td>

                            <!-- SLA Target -->
                            <td>
                                @if($ticket->sla_due_at)
                                    @if(in_array($ticket->status->value, ['resolved', 'closed']))
                                        <span class="text-success small fw-medium">
                                            <i class="bi bi-check-circle me-1"></i>Completed
                                        </span>
                                    @elseif($isBreached)
                                        <span class="badge badge-soft-danger rounded-pill px-2 py-1">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>SLA Breached
                                        </span>
                                    @else
                                        <div class="small fw-medium text-dark d-flex align-items-center gap-1">
                                            <i class="bi bi-clock-history text-primary"></i>
                                            <span>{{ $ticket->sla_due_at->diffForHumans() }}</span>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">{{ $ticket->sla_due_at->format('M d, H:i') }}</div>
                                    @endif
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>

                            <!-- Submitted Date -->
                            <td>
                                <div class="small text-dark fw-medium">{{ $ticket->created_at->format('M d, Y') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $ticket->created_at->format('h:i A') }}</div>
                            </td>

                            <!-- Actions -->
                            <td class="text-end pe-4">
                                <a href="{{ route('customer.tickets.show', $ticket) }}" class="btn btn-sm btn-light border text-secondary fw-semibold px-3 rounded-3 shadow-none">
                                    <span>Details</span>
                                    <i class="bi bi-chevron-right ms-1 text-muted" style="font-size: 0.75rem;"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="p-3 px-4 border-top bg-light d-flex justify-content-end">
                {{ $tickets->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

@section('extra_js')
<script>
    function filterTicketsTable() {
        const query = document.getElementById('ticketSearchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#customerTicketsTable tbody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }
</script>
@endsection
