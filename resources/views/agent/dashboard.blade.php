@extends('layouts.app')

@section('title', 'Agent Dashboard')
@section('portal_name', 'Agent Support Workstation')
@section('user_name', Auth::guard('agent')->user()?->name ?? 'Support Agent')

@section('logout_button')
<form method="POST" action="{{ route('agent.logout') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-light px-3 py-1 rounded-pill" style="font-size: 0.82rem;">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
    </button>
</form>
@endsection

@section('content')
<!-- Header Banner -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small text-muted mb-1">
                <li class="breadcrumb-item"><a href="{{ route('agent.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-headset me-1"></i>Agent</a></li>
                <li class="breadcrumb-item active text-teal fw-medium" aria-current="page">Dashboard</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1 display-font text-dark">Agent Workstation</h2>
        <p class="text-muted small mb-0">Track your assigned workload, atomic triage queue, and critical SLA deadlines.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-secondary border px-3 py-2 rounded-3 shadow-sm small">
            <span class="live-pulse me-1"></span> Live Queue Active
        </span>
    </div>
</div>

<!-- Workload KPI Cards -->
<div class="row g-3 mb-4">
    <!-- My Active Assigned -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">My Assigned</span>
                <div class="stat-icon" style="background: rgba(13, 148, 136, 0.1); color: #0d9488;">
                    <i class="bi bi-person-workspace"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-dark lh-1 mb-1">{{ $stats->active_count ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.78rem;">Active in your queue</div>
        </div>
    </div>

    <!-- Unassigned Triage Pool -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Unassigned Pool</span>
                <div class="stat-icon" style="background: rgba(2, 132, 199, 0.1); color: #0284c7;">
                    <i class="bi bi-inbox-fill"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-info lh-1 mb-1">{{ $unassignedCount }}</div>
            <div class="text-muted small" style="font-size: 0.78rem;">Available to claim</div>
        </div>
    </div>

    <!-- SLA Warning Risks -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">SLA Warnings</span>
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-warning lh-1 mb-1">{{ $stats->approaching_count ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.78rem;">Require immediate action</div>
        </div>
    </div>

    <!-- Resolved by Me -->
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Resolved by Me</span>
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                    <i class="bi bi-check2-circle"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-success lh-1 mb-1">{{ $stats->resolved_count ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.78rem;">Total tickets resolved</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: My Current Tickets -->
    <div class="col-lg-8">
        <div class="modern-card">
            <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-list-task text-teal fs-5" style="color: var(--portal-primary);"></i>
                    <h5 class="fw-bold mb-0 text-dark display-font fs-6">My Assigned Tickets</h5>
                </div>
                <span class="badge badge-soft-primary rounded-pill px-2 py-1">{{ $myTickets->count() }} tickets</span>
            </div>

            @if($myTickets->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-cup-hot fs-2 d-block mb-2 text-muted"></i>
                    <h6 class="fw-bold text-dark">Your queue is clear!</h6>
                    <p class="small text-muted mb-0">No active tickets assigned to you right now. Check the unassigned pool.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Ticket ID</th>
                                <th>Subject</th>
                                <th>Customer</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myTickets as $ticket)
                                @php
                                    $pClass = match($ticket->priority->value) {
                                        'critical' => 'badge-soft-danger',
                                        'high' => 'badge-soft-warning',
                                        'medium' => 'badge-soft-info',
                                        'low' => 'badge-soft-success',
                                        default => 'badge-soft-secondary',
                                    };
                                    $sClass = match($ticket->status->value) {
                                        'open' => 'badge-soft-primary',
                                        'in_progress' => 'badge-soft-warning',
                                        'waiting_for_customer' => 'badge-soft-info',
                                        'resolved' => 'badge-soft-success',
                                        'closed' => 'badge-soft-secondary',
                                        default => 'badge-soft-secondary',
                                    };
                                @endphp
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-dark small">{{ $ticket->ticket_number }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark small text-truncate" style="max-width: 250px;">
                                            {{ $ticket->subject }}
                                        </div>
                                        <span class="text-muted" style="font-size: 0.72rem;">{{ $ticket->category->name }}</span>
                                    </td>
                                    <td class="small text-secondary">{{ $ticket->customer?->name ?? 'Customer' }}</td>
                                    <td>
                                        <span class="badge {{ $pClass }} rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            {{ $ticket->priority->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $sClass }} rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            {{ $ticket->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Right Column: Agent Information & Shortcuts -->
    <div class="col-lg-4">
        <!-- SLA Guidelines Card -->
        <div class="modern-card p-4 mb-4">
            <h6 class="fw-bold text-dark display-font mb-3">SLA Response Standards</h6>
            <div class="d-flex flex-column gap-2 small">
                <div class="d-flex justify-content-between p-2 rounded bg-light">
                    <span class="text-danger fw-semibold"><i class="bi bi-lightning-charge-fill me-1"></i>Critical:</span>
                    <strong class="text-dark">4 Business Hours</strong>
                </div>
                <div class="d-flex justify-content-between p-2 rounded bg-light">
                    <span class="text-warning fw-semibold"><i class="bi bi-exclamation-circle-fill me-1"></i>High:</span>
                    <strong class="text-dark">8 Business Hours</strong>
                </div>
                <div class="d-flex justify-content-between p-2 rounded bg-light">
                    <span class="text-info fw-semibold"><i class="bi bi-dash-circle-fill me-1"></i>Medium:</span>
                    <strong class="text-dark">24 Business Hours</strong>
                </div>
                <div class="d-flex justify-content-between p-2 rounded bg-light">
                    <span class="text-success fw-semibold"><i class="bi bi-info-circle-fill me-1"></i>Low:</span>
                    <strong class="text-dark">48 Business Hours</strong>
                </div>
            </div>
        </div>

        <!-- Quick Switcher -->
        <div class="modern-card p-4">
            <h6 class="fw-bold text-dark display-font mb-3">Workstation Navigation</h6>
            <div class="d-grid gap-2">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-grid me-2" style="color: var(--portal-primary);"></i> Portal Switcher</span>
                    <i class="bi bi-arrow-right text-muted"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
