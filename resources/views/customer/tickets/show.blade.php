@extends('layouts.app')

@section('title', "Ticket {$ticket->ticket_number} - Customer Portal")
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
@php
    $statusConfig = match($ticket->status->value) {
        'open' => ['class' => 'badge-soft-primary', 'icon' => 'bi-circle-fill', 'pulse' => true],
        'in_progress' => ['class' => 'badge-soft-warning', 'icon' => 'bi-hourglass-split', 'pulse' => false],
        'waiting_for_customer' => ['class' => 'badge-soft-info', 'icon' => 'bi-person-lines-fill', 'pulse' => false],
        'resolved' => ['class' => 'badge-soft-success', 'icon' => 'bi-check2-circle', 'pulse' => false],
        'closed' => ['class' => 'badge-soft-secondary', 'icon' => 'bi-lock-fill', 'pulse' => false],
        default => ['class' => 'badge-soft-secondary', 'icon' => 'bi-circle', 'pulse' => false],
    };

    $priorityBadge = match($ticket->priority->value) {
        'critical' => ['class' => 'badge-soft-danger', 'icon' => 'bi-lightning-charge-fill'],
        'high' => ['class' => 'badge-soft-warning', 'icon' => 'bi-exclamation-circle-fill'],
        'medium' => ['class' => 'badge-soft-info', 'icon' => 'bi-dash-circle-fill'],
        'low' => ['class' => 'badge-soft-success', 'icon' => 'bi-info-circle-fill'],
        default => ['class' => 'badge-soft-secondary', 'icon' => 'bi-flag-fill'],
    };

    $isBreached = $ticket->sla_due_at && $ticket->sla_due_at->isPast() && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
    $isApproaching = $ticket->sla_warning_at && $ticket->sla_warning_at->isPast() && !$isBreached && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
@endphp

<!-- Hero Header Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
    <!-- Header Top Strip -->
    <div class="p-3 px-4 bg-light border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small text-muted mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('customer.dashboard') }}" class="text-decoration-none text-muted">
                        <i class="bi bi-house me-1"></i>Portal
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('customer.tickets.index') }}" class="text-decoration-none text-muted">My Tickets</a>
                </li>
                <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">{{ $ticket->ticket_number }}</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('customer.tickets.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-medium shadow-none">
                <i class="bi bi-arrow-left me-1"></i> Back to My Tickets
            </a>
        </div>
    </div>

    <!-- Header Main Body -->
    <div class="p-4 p-md-5">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <!-- Ticket Reference Number & Copy Button -->
            <div class="d-inline-flex align-items-center bg-light border rounded-pill px-3 py-1">
                <span class="font-monospace fw-bold text-dark fs-6">{{ $ticket->ticket_number }}</span>
                <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-muted text-decoration-none shadow-none" 
                        onclick="navigator.clipboard.writeText('{{ $ticket->ticket_number }}'); this.innerHTML = '<i class=\'bi bi-check text-success\'></i>'; setTimeout(() => this.innerHTML = '<i class=\'bi bi-copy\'></i>', 1500);" 
                        title="Copy Ticket ID">
                    <i class="bi bi-copy"></i>
                </button>
            </div>

            <!-- Status Badge -->
            <span class="badge {{ $statusConfig['class'] }} rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">
                @if($statusConfig['pulse'])
                    <span class="live-pulse me-1"></span>
                @else
                    <i class="bi {{ $statusConfig['icon'] }} me-1"></i>
                @endif
                {{ $ticket->status->label() }}
            </span>

            <!-- Priority Badge -->
            <span class="badge {{ $priorityBadge['class'] }} rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">
                <i class="bi {{ $priorityBadge['icon'] }}"></i>
                {{ $ticket->priority->label() }} Priority
            </span>

            <!-- Category Badge -->
            <span class="badge bg-white text-secondary border rounded-pill px-3 py-2 fw-normal d-inline-flex align-items-center gap-1">
                <i class="bi bi-tag-fill text-muted"></i>
                {{ $ticket->category->name }}
            </span>

            @if($isBreached)
                <span class="badge badge-soft-danger rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">
                    <span class="live-pulse-danger me-1"></span> SLA Breached
                </span>
            @elseif($isApproaching)
                <span class="badge badge-soft-warning rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1">
                    <i class="bi bi-exclamation-triangle-fill"></i> SLA Warning
                </span>
            @endif
        </div>

        <!-- Subject Headline -->
        <h2 class="fw-bold mb-3 text-dark display-font lh-sm">{{ $ticket->subject }}</h2>

        <!-- Metadata Strip -->
        <div class="d-flex flex-wrap align-items-center gap-3 text-muted small pt-2 border-top">
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-person-check text-primary"></i>
                <span>Created by: <strong class="text-dark">{{ $ticket->customer?->name ?? 'You' }}</strong></span>
            </div>
            <span class="text-secondary opacity-50">&bull;</span>
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-clock-history text-secondary"></i>
                <span>Submitted: <strong class="text-dark">{{ $ticket->created_at->format('M d, Y h:i A') }}</strong></span>
            </div>
            <span class="text-secondary opacity-50">&bull;</span>
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-headset text-info"></i>
                <span>Assigned: 
                    @if($ticket->assignedAgent)
                        <strong class="text-dark">{{ $ticket->assignedAgent->name }}</strong>
                    @else
                        <span class="badge bg-light text-muted border">Support Queue</span>
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 px-4">
    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
        <i class="bi bi-check-lg"></i>
    </div>
    <div class="fw-medium text-success">{{ session('success') }}</div>
</div>
@endif

<!-- Resolved Status Callout / Confirmation Banner -->
@if($ticket->status->value === 'resolved')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border-left: 5px solid #10b981 !important;">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white shadow-sm flex-shrink-0" style="width: 46px; height: 46px; font-size: 1.4rem;">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div>
                <h5 class="fw-bold text-success mb-1 display-font">Solution Provided — Confirm &amp; Close</h5>
                <p class="text-success small mb-0 opacity-80" style="font-size: 0.88rem;">
                    Our support team has resolved this ticket. If you are satisfied with the resolution, please confirm to close the ticket.
                </p>
            </div>
        </div>
        <form method="POST" action="{{ route('customer.tickets.close', $ticket) }}" class="m-0 flex-shrink-0">
            @csrf
            <button type="submit" class="btn btn-success px-4 py-2 rounded-3 shadow-sm fw-semibold d-inline-flex align-items-center gap-2">
                <i class="bi bi-check2-circle fs-5"></i>
                <span>Confirm Resolution &amp; Close</span>
            </button>
        </form>
    </div>
</div>
@elseif($ticket->status->value === 'closed')
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-light text-muted border">
    <div class="card-body p-3 px-4 d-flex align-items-center gap-3">
        <i class="bi bi-lock-fill fs-4 text-secondary"></i>
        <div class="small">
            <strong>This ticket is closed.</strong> This issue is archived and no further replies can be added. If you need assistance with a new question, please submit a new ticket.
        </div>
    </div>
</div>
@endif

<div class="row g-4">
    <!-- Main Ticket Thread & Details Column -->
    <div class="col-lg-8">
        <!-- Ticket Original Narrative Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white">
            <div class="p-3 px-4 border-bottom bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="navbar-brand-badge" style="width: 32px; height: 32px; font-size: 0.85rem;">
                        <i class="bi bi-chat-left-text-fill"></i>
                    </div>
                    <div>
                        <span class="fw-bold text-dark small">Original Inquiry</span>
                        <span class="text-muted small ms-1">&bull; Submitted {{ $ticket->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <span class="badge bg-white text-secondary border small">
                    <i class="bi bi-folder2 me-1"></i>{{ $ticket->category->name }}
                </span>
            </div>
            
            <div class="p-4 p-md-4">
                <div class="text-dark" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.7;">{{ $ticket->description }}</div>
            </div>

            @if($ticket->attachments->isNotEmpty())
                <div class="p-3 px-4 border-top bg-light">
                    <div class="small fw-bold text-dark mb-2 d-flex align-items-center gap-1">
                        <i class="bi bi-paperclip text-primary"></i>
                        <span>Attached Files ({{ $ticket->attachments->count() }})</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($ticket->attachments as $attachment)
                            @php
                                $ext = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
                                $isImg = in_array($ext, ['png', 'jpg', 'jpeg']);
                                $isPdf = $ext === 'pdf';
                                $sizeKb = round($attachment->size_bytes / 1024, 0);
                            @endphp
                            <div class="p-2 px-3 rounded-3 border bg-white d-flex align-items-center gap-2 shadow-sm">
                                <i class="bi {{ $isImg ? 'bi-file-earmark-image text-primary' : ($isPdf ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-text text-secondary') }} fs-5"></i>
                                <div>
                                    <a href="{{ route('attachments.download', $attachment) }}" class="text-decoration-none text-dark fw-semibold small d-block">
                                        {{ $attachment->original_name }}
                                    </a>
                                    <span class="text-muted" style="font-size: 0.72rem;">{{ $sizeKb }} KB</span>
                                </div>
                                <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-sm btn-light border py-1 px-2 ms-2 text-secondary" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Replies Thread Component -->
        @include('tickets._replies_thread', ['replies' => $ticket->replies, 'ticket' => $ticket, 'portal' => 'customer'])

        <!-- Activity & Event Audit Timeline Component -->
        @include('tickets._activity_timeline', ['activities' => $ticket->activities])
    </div>

    <!-- Ticket Sidebar Meta Column -->
    <div class="col-lg-4">
        <!-- SLA Status Widget Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold text-dark display-font fs-6">SLA Resolution Target</span>
                @if($isBreached)
                    <span class="badge badge-soft-danger rounded-pill px-2 py-1"><i class="bi bi-x-circle me-1"></i>Breached</span>
                @elseif(in_array($ticket->status->value, ['resolved', 'closed']))
                    <span class="badge badge-soft-success rounded-pill px-2 py-1"><i class="bi bi-check2-circle me-1"></i>Met</span>
                @else
                    <span class="badge badge-soft-primary rounded-pill px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Active</span>
                @endif
            </div>

            <div class="p-3 rounded-3 bg-light border text-center mb-3">
                <div class="text-muted small text-uppercase fw-bold letter-spacing-1 mb-1" style="font-size: 0.72rem;">Resolution Target</div>
                @if($ticket->sla_due_at)
                    <div class="display-font fw-bold fs-4 {{ $isBreached ? 'text-danger' : 'text-primary' }}">
                        {{ $ticket->sla_due_at->diffForHumans() }}
                    </div>
                    <div class="small text-muted mt-1">
                        {{ $ticket->sla_due_at->format('M d, Y - h:i A') }}
                    </div>
                @else
                    <div class="text-muted">&mdash;</div>
                @endif
            </div>

            <div class="small text-muted d-flex flex-column gap-2">
                <div class="d-flex justify-content-between">
                    <span>Priority SLA Window:</span>
                    <strong class="text-dark">{{ $ticket->priority->slaMinutes() / 60 }} Business Hours</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Warning Notice:</span>
                    <strong class="text-dark">{{ $ticket->sla_warning_at ? $ticket->sla_warning_at->format('M d, H:i') : '&mdash;' }}</strong>
                </div>
            </div>
        </div>

        <!-- Ticket Overview Properties Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <h6 class="fw-bold text-dark display-font mb-3">Ticket Information</h6>

            <div class="d-flex flex-column gap-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Assigned Specialist</span>
                    <div class="mt-1">
                        @if($ticket->assignedAgent)
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width: 26px; height: 26px; font-size: 0.7rem; background: var(--portal-gradient); color: white;">
                                    {{ strtoupper(substr($ticket->assignedAgent->name, 0, 1)) }}
                                </div>
                                <span class="fw-semibold text-dark small">{{ $ticket->assignedAgent->name }}</span>
                            </div>
                        @else
                            <span class="badge badge-soft-secondary rounded-pill px-2 py-1">
                                <i class="bi bi-hourglass me-1"></i> Support Queue (Pending Assignment)
                            </span>
                        @endif
                    </div>
                </div>

                <hr class="my-1 text-light">

                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Category</span>
                    <span class="fw-semibold text-dark small mt-1 d-block">{{ $ticket->category->name }}</span>
                </div>

                <hr class="my-1 text-light">

                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Submitted Timestamp</span>
                    <span class="text-secondary small mt-1 d-block">{{ $ticket->created_at->format('M d, Y - h:i A') }}</span>
                </div>

                <hr class="my-1 text-light">

                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Last Updated</span>
                    <span class="text-secondary small mt-1 d-block">{{ $ticket->updated_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>

        <!-- Help Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white" style="border-top: 3px solid var(--portal-primary) !important;">
            <div class="d-flex align-items-center gap-2 mb-2 text-primary fw-bold">
                <i class="bi bi-info-circle-fill"></i>
                <span class="small text-uppercase" style="letter-spacing: 0.05em;">Customer Notice</span>
            </div>
            <p class="text-muted small mb-0" style="line-height: 1.6;">
                Any reply you post is instantly routed to our support team. When our staff provides a solution, you will be invited to confirm resolution and close the ticket.
            </p>
        </div>
    </div>
</div>
@endsection
