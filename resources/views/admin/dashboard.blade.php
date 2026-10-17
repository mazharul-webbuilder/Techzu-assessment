@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('portal_name', 'System Admin Console')
@section('user_name', Auth::guard('admin')->user()?->name ?? 'Administrator')

@section('logout_button')
<form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
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
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-shield-lock me-1"></i>Admin</a></li>
                <li class="breadcrumb-item active text-danger fw-medium" aria-current="page">Dashboard</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1 display-font text-dark">System Command Center</h2>
        <p class="text-muted small mb-0">Live aggregate metrics, SLA compliance tracking, and support queue overview.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-secondary border px-3 py-2 rounded-3 shadow-sm small">
            <span class="live-pulse me-1"></span> SLA Scheduler Live
        </span>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <!-- Total Active Open -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Active Open</span>
                <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5; width: 36px; height: 36px; font-size: 1.1rem;">
                    <i class="bi bi-inbox-fill"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-dark lh-1 mb-1">{{ $stats->open_total ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.72rem;">Tickets in progress</div>
        </div>
    </div>

    <!-- Unassigned Pool -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Unassigned</span>
                <div class="stat-icon" style="background: rgba(14, 165, 233, 0.1); color: #0284c7; width: 36px; height: 36px; font-size: 1.1rem;">
                    <i class="bi bi-person-x-fill"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-info lh-1 mb-1">{{ $stats->unassigned ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.72rem;">Awaiting triage/claim</div>
        </div>
    </div>

    <!-- Critical Open -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Critical</span>
                <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #dc2626; width: 36px; height: 36px; font-size: 1.1rem;">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-danger lh-1 mb-1">{{ $stats->critical_open ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.72rem;">Top priority urgency</div>
        </div>
    </div>

    <!-- SLA Warning / Approaching -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Approaching</span>
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: #d97706; width: 36px; height: 36px; font-size: 1.1rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-warning lh-1 mb-1">{{ $stats->approaching ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.72rem;">&gt;80% SLA time elapsed</div>
        </div>
    </div>

    <!-- SLA Breached -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Breached</span>
                <div class="stat-icon" style="background: rgba(220, 38, 38, 0.12); color: #dc2626; width: 36px; height: 36px; font-size: 1.1rem;">
                    <i class="bi bi-x-octagon-fill"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-danger lh-1 mb-1 d-flex align-items-center gap-1">
                <span>{{ $stats->breached ?? 0 }}</span>
                @if(($stats->breached ?? 0) > 0)
                    <span class="live-pulse-danger"></span>
                @endif
            </div>
            <div class="text-muted small" style="font-size: 0.72rem;">Target time exceeded</div>
        </div>
    </div>

    <!-- Resolved Total -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Resolved</span>
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #059669; width: 36px; height: 36px; font-size: 1.1rem;">
                    <i class="bi bi-check2-all"></i>
                </div>
            </div>
            <div class="display-font fw-bold fs-3 text-success lh-1 mb-1">{{ $stats->resolved_total ?? 0 }}</div>
            <div class="text-muted small" style="font-size: 0.72rem;">Closed or resolved</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Recent Tickets & Queue Monitor -->
    <div class="col-lg-8">
        <div class="modern-card">
            <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-ticket-perforated-fill text-danger fs-5"></i>
                    <h5 class="fw-bold mb-0 text-dark display-font fs-6">Live Ticket Feed</h5>
                </div>
                <span class="badge badge-soft-secondary rounded-pill px-2 py-1">6 Latest</span>
            </div>

            @if($recentTickets->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                    <p class="small mb-0">No tickets currently in the database.</p>
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
                                <th>Assigned Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentTickets as $ticket)
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
                                        <div class="fw-semibold text-dark small text-truncate" style="max-width: 220px;">
                                            {{ $ticket->subject }}
                                        </div>
                                        <span class="text-muted" style="font-size: 0.72rem;">{{ $ticket->category->name }}</span>
                                    </td>
                                    <td class="small text-secondary">{{ $ticket->customer?->name ?? 'Unknown' }}</td>
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
                                    <td class="small">
                                        @if($ticket->assignedAgent)
                                            <span class="text-dark fw-medium">{{ $ticket->assignedAgent->name }}</span>
                                        @else
                                            <span class="text-muted fst-italic">Unassigned</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Right Column: Quick Administration Modules -->
    <div class="col-lg-4">
        <!-- System Health & Overview -->
        <div class="modern-card p-4 mb-4">
            <h6 class="fw-bold text-dark display-font mb-3">Support Infrastructure</h6>
            <div class="d-flex flex-column gap-3">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people-fill text-primary fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark small">Staff Agents</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Support team capacity</div>
                        </div>
                    </div>
                    <span class="badge bg-white text-dark border fw-bold fs-6">{{ $agentsCount }}</span>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-tags-fill text-success fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark small">Active Categories</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Routing taxonomy</div>
                        </div>
                    </div>
                    <span class="badge bg-white text-dark border fw-bold fs-6">{{ $categoriesCount }}</span>
                </div>

                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-warning fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark small">Business Hours SLA</div>
                            <div class="text-muted" style="font-size: 0.72rem;">Mon-Fri 09:00 - 18:00 (Europe/London)</div>
                        </div>
                    </div>
                    <span class="badge badge-soft-success">Active</span>
                </div>
            </div>
        </div>

        <!-- Management Navigation Hub -->
        <div class="modern-card p-4">
            <h6 class="fw-bold text-dark display-font mb-3">Admin Actions</h6>
            <div class="d-grid gap-2">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-grid me-2 text-danger"></i> Portal Switcher</span>
                    <i class="bi bi-arrow-right text-muted"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
