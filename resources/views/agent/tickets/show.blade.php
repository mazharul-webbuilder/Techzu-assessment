@extends('layouts.app')

@section('title', "Ticket {$ticket->ticket_number} - Workstation")
@section('portal_name', 'Agent Workstation')
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
@php
    $currentAgent = Auth::guard('agent')->user();
    $isAssignedToMe = $currentAgent && $ticket->assigned_agent_id === $currentAgent->id;
    $isUnassigned = is_null($ticket->assigned_agent_id);

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

    $validTransitions = collect(\App\Enums\TicketStatus::cases())->filter(function ($statusOption) use ($ticket) {
        return $ticket->status->canTransitionTo($statusOption) && $ticket->status !== $statusOption;
    });
@endphp

<!-- Hero Header Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white overflow-hidden">
    <!-- Header Top Strip -->
    <div class="p-3 px-4 bg-light border-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small text-muted mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('agent.dashboard') }}" class="text-decoration-none text-muted">
                        <i class="bi bi-headset me-1"></i>Workstation
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('agent.tickets.index') }}" class="text-decoration-none text-muted">Ticket Queue</a>
                </li>
                <li class="breadcrumb-item active fw-medium" style="color: #0d9488;" aria-current="page">{{ $ticket->ticket_number }}</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-2">
            @if($isUnassigned)
                <form method="POST" action="{{ route('agent.tickets.claim', $ticket) }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-sm text-white rounded-pill px-3 py-1 fw-semibold shadow-sm d-inline-flex align-items-center gap-1" style="background: #0d9488;">
                        <i class="bi bi-hand-index-thumb"></i> Claim This Ticket
                    </button>
                </form>
            @endif
            <a href="{{ route('agent.tickets.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-medium shadow-none">
                <i class="bi bi-arrow-left me-1"></i> Back to Queue
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
                {{ $ticket->category?->name ?? 'General' }}
            </span>

            @if($ticket->escalated_at)
                <span class="badge bg-danger text-white rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1" title="Escalated: {{ $ticket->escalated_at }}">
                    <i class="bi bi-shield-fill-exclamation"></i> Escalated
                </span>
            @endif

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
        <div class="d-flex flex-wrap align-items-center gap-3 text-muted small pt-3 border-top">
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-person-circle text-primary"></i>
                <span>Customer: <strong class="text-dark">{{ $ticket->customer?->name ?? 'Customer' }}</strong></span>
            </div>
            <span class="text-secondary opacity-50">&bull;</span>
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-clock-history text-secondary"></i>
                <span>Submitted: <strong class="text-dark">{{ $ticket->created_at->format('M d, Y h:i A') }}</strong></span>
            </div>
            <span class="text-secondary opacity-50">&bull;</span>
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-headset text-info"></i>
                <span>Assignment: 
                    @if($isAssignedToMe)
                        <span class="badge rounded-pill text-white px-2 py-1" style="background: #0d9488;">Assigned to You</span>
                    @elseif($ticket->assignedAgent)
                        <strong class="text-dark">{{ $ticket->assignedAgent->name }}</strong>
                    @else
                        <span class="badge badge-soft-warning rounded-pill px-2 py-1">Unassigned in Queue</span>
                    @endif
                </span>
            </div>
            <span class="text-secondary opacity-50">&bull;</span>
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-arrow-clockwise text-secondary"></i>
                <span>Last Updated: <strong class="text-dark">{{ $ticket->updated_at->diffForHumans() }}</strong></span>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 px-4">
    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
        <i class="bi bi-check-lg"></i>
    </div>
    <div class="fw-medium text-success">{{ session('success') }}</div>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center gap-3 p-3 px-4">
    <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
        <i class="bi bi-exclamation-triangle-fill"></i>
    </div>
    <div class="fw-medium text-danger">{{ session('error') }}</div>
</div>
@endif

@if($isUnassigned)
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #f0fdfa 0%, #e6fffa 100%); border-left: 5px solid #0d9488 !important;">
    <div class="card-body p-3 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.25rem; background: #0d9488;">
                <i class="bi bi-hand-index-thumb"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1 display-font" style="color: #0f766e;">Ticket Unassigned in Queue</h6>
                <div class="small opacity-85" style="color: #115e59;">
                    This ticket is waiting for a specialist. Claim it now to take ownership and manage communication with the customer.
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('agent.tickets.claim', $ticket) }}" class="m-0 flex-shrink-0">
            @csrf
            <button type="submit" class="btn text-white px-4 py-2 rounded-3 shadow-sm fw-semibold d-inline-flex align-items-center gap-2" style="background: #0d9488;">
                <i class="bi bi-check2-circle fs-5"></i>
                <span>Claim This Ticket</span>
            </button>
        </form>
    </div>
</div>
@endif

@if($ticket->escalated_at)
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #fef2f2 0%, #fff1f2 100%); border-left: 5px solid #dc2626 !important;">
    <div class="card-body p-3 px-4 d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger text-white shadow-sm flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.25rem;">
            <i class="bi bi-shield-fill-exclamation"></i>
        </div>
        <div>
            <h6 class="fw-bold text-danger mb-1 display-font">Management Escalation Active</h6>
            <div class="text-danger small opacity-85">
                Target resolution window was breached. Escalated to supervision on <strong>{{ $ticket->escalated_at->format('M d, Y - h:i A') }}</strong>. Prompt response and resolution required.
            </div>
        </div>
    </div>
</div>
@endif

@if($ticket->status->value === 'resolved')
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border-left: 5px solid #10b981 !important;">
    <div class="card-body p-3 px-4 d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white shadow-sm flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.25rem;">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div>
            <h6 class="fw-bold text-success mb-1 display-font">Ticket Marked as Resolved</h6>
            <div class="text-success small opacity-85">
                Resolution recorded on <strong>{{ $ticket->resolved_at ? $ticket->resolved_at->format('M d, Y - h:i A') : $ticket->updated_at->format('M d, Y - h:i A') }}</strong>. Customer was invited to review and confirm closure.
            </div>
        </div>
    </div>
</div>
@elseif($ticket->status->value === 'closed')
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-light text-muted border">
    <div class="card-body p-3 px-4 d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white shadow-sm flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.2rem;">
            <i class="bi bi-lock-fill"></i>
        </div>
        <div class="small">
            <strong class="text-dark">This ticket is closed and archived.</strong> The issue lifecycle is concluded. SLA timers are terminated.
        </div>
    </div>
</div>
@endif

<div class="row g-4">
    <!-- Main Ticket Thread & Activity Column -->
    <div class="col-lg-8">
        <!-- Customer Request Narrative Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden bg-white">
            <div class="p-3 px-4 border-bottom bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-circle" style="width: 32px; height: 32px; font-size: 0.85rem; background: var(--portal-gradient); color: #fff;">
                        {{ strtoupper(substr($ticket->customer?->name ?? 'C', 0, 1)) }}
                    </div>
                    <div>
                        <span class="fw-bold text-dark small">{{ $ticket->customer?->name ?? 'Customer' }}</span>
                        <span class="text-muted small ms-1">&bull; Submitted {{ $ticket->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <span class="badge bg-white text-secondary border small">
                    <i class="bi bi-tag me-1" style="color: #0d9488;"></i>{{ $ticket->category?->name ?? 'General' }}
                </span>
            </div>
            
            <div class="p-4 p-md-4">
                <div class="text-dark" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.7;">{{ $ticket->description }}</div>
            </div>

            @if($ticket->attachments->isNotEmpty())
                <div class="p-3 px-4 border-top bg-light">
                    <div class="small fw-bold text-dark mb-2 d-flex align-items-center gap-1">
                        <i class="bi bi-paperclip text-primary"></i>
                        <span>Attachments ({{ $ticket->attachments->count() }})</span>
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

        <!-- Replies & Internal Notes Thread Component -->
        @include('tickets._replies_thread', ['replies' => $ticket->replies, 'ticket' => $ticket, 'portal' => 'agent'])

        <!-- Activity & Event Audit Timeline Component -->
        @include('tickets._activity_timeline', ['activities' => $ticket->activities])
    </div>

    <!-- Right Sidebar Meta Column -->
    <div class="col-lg-4">
        <!-- Agent Workstation Controls Card -->
        @if($isAssignedToMe)
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white" style="border-top: 3px solid #0d9488 !important;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="fw-bold text-dark display-font mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-sliders" style="color: #0d9488;"></i>
                        <span>Workstation Actions</span>
                    </h6>
                    <span class="badge rounded-pill text-white px-2 py-1" style="font-size: 0.7rem; background: #0d9488;">Assigned to You</span>
                </div>

                <!-- Status Transition Form -->
                <div class="mb-3">
                    <form method="POST" action="{{ route('agent.tickets.status', $ticket) }}">
                        @csrf
                        <label for="statusSelect" class="form-label small text-muted fw-semibold d-flex align-items-center gap-1 mb-1" style="font-size: 0.78rem;">
                            <i class="bi bi-arrow-repeat text-primary"></i> Status Transition
                        </label>
                        <div class="input-group input-group-sm">
                            <select name="status" id="statusSelect" class="form-select" {{ $validTransitions->isEmpty() ? 'disabled' : 'required' }}>
                                @if($validTransitions->isEmpty())
                                    <option value="">No transitions available</option>
                                @else
                                    <option value="">-- Choose Next State --</option>
                                    @foreach($validTransitions as $statusOption)
                                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <button type="submit" class="btn text-white px-3 shadow-none fw-medium" style="background: #0d9488;" {{ $validTransitions->isEmpty() ? 'disabled' : '' }}>
                                Update
                            </button>
                        </div>
                    </form>
                </div>

                <hr class="my-3 text-light">

                <!-- Priority Level Form -->
                <div>
                    <form method="POST" action="{{ route('agent.tickets.priority', $ticket) }}">
                        @csrf
                        <label for="prioritySelect" class="form-label small text-muted fw-semibold d-flex align-items-center gap-1 mb-1" style="font-size: 0.78rem;">
                            <i class="bi bi-flag-fill text-warning"></i> Priority Level &amp; SLA
                        </label>
                        <div class="input-group input-group-sm">
                            <select name="priority" id="prioritySelect" class="form-select" required>
                                @foreach(\App\Enums\Priority::cases() as $priorityOption)
                                    <option value="{{ $priorityOption->value }}" {{ $ticket->priority === $priorityOption ? 'selected' : '' }}>
                                        {{ $priorityOption->label() }} ({{ $priorityOption->slaMinutes() / 60 }}h SLA)
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline-secondary px-3 shadow-none fw-medium">
                                Apply
                            </button>
                        </div>
                        <span class="text-muted small mt-1 d-block" style="font-size: 0.72rem;">Changing priority updates the SLA resolution target immediately.</span>
                    </form>
                </div>
            </div>
        @elseif($isUnassigned)
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white" style="border-top: 3px solid #0d9488 !important;">
                <h6 class="fw-bold text-dark display-font mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-hand-index-thumb" style="color: #0d9488;"></i>
                    <span>Claim Ticket</span>
                </h6>
                <p class="text-muted small mb-3">This ticket currently has no assigned specialist. Claim it to move it into your workstation and enable status updates.</p>
                <form method="POST" action="{{ route('agent.tickets.claim', $ticket) }}">
                    @csrf
                    <button type="submit" class="btn text-white w-100 py-2 rounded-3 fw-semibold shadow-sm d-inline-flex align-items-center justify-content-center gap-2" style="background: #0d9488;">
                        <i class="bi bi-check2-circle fs-5"></i>
                        <span>Claim This Ticket</span>
                    </button>
                </form>
            </div>
        @else
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-info-circle-fill text-secondary"></i>
                    <h6 class="fw-bold text-dark display-font mb-0">Assigned Specialist</h6>
                </div>
                <div class="p-3 bg-light rounded-3 border d-flex align-items-center gap-2">
                    <div class="avatar-circle" style="width: 32px; height: 32px; font-size: 0.8rem; background: var(--portal-gradient); color: #fff;">
                        {{ strtoupper(substr($ticket->assignedAgent?->name ?? 'A', 0, 1)) }}
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">{{ $ticket->assignedAgent?->name ?? 'Another Agent' }}</div>
                        <span class="text-muted small" style="font-size: 0.72rem;">Managing this ticket</span>
                    </div>
                </div>
            </div>
        @endif

        <!-- SLA Target Monitor Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold text-dark display-font fs-6">SLA Resolution Target</span>
                @if($isBreached)
                    <span class="badge badge-soft-danger rounded-pill px-2 py-1"><i class="bi bi-x-circle me-1"></i>Breached</span>
                @elseif(in_array($ticket->status->value, ['resolved', 'closed']))
                    <span class="badge badge-soft-success rounded-pill px-2 py-1"><i class="bi bi-check2-circle me-1"></i>Met</span>
                @elseif($isApproaching)
                    <span class="badge badge-soft-warning rounded-pill px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Warning</span>
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
                    <span>Warning Notice (80%):</span>
                    <strong class="text-dark">{{ $ticket->sla_warning_at ? $ticket->sla_warning_at->format('M d, h:i A') : '&mdash;' }}</strong>
                </div>
                @if($ticket->escalated_at)
                    <div class="d-flex justify-content-between">
                        <span class="text-danger">Escalated On:</span>
                        <strong class="text-danger">{{ $ticket->escalated_at->format('M d, h:i A') }}</strong>
                    </div>
                @endif
            </div>
        </div>

        <!-- Customer Profile Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <h6 class="fw-bold text-dark display-font mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-person-vcard" style="color: #0d9488;"></i>
                <span>Customer Profile</span>
            </h6>

            <div class="d-flex flex-column gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle" style="width: 40px; height: 40px; font-size: 0.95rem; background: var(--portal-gradient); color: #fff;">
                        {{ strtoupper(substr($ticket->customer?->name ?? 'C', 0, 1)) }}
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">{{ $ticket->customer?->name ?? 'Guest / Customer' }}</div>
                        <a href="mailto:{{ $ticket->customer?->email }}" class="text-muted small text-decoration-none d-block text-truncate" style="max-width: 200px;">
                            <i class="bi bi-envelope me-1"></i>{{ $ticket->customer?->email ?? 'No email' }}
                        </a>
                    </div>
                </div>

                @if($ticket->customer?->phone)
                    <hr class="my-1 text-light">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted"><i class="bi bi-telephone me-1"></i> Phone:</span>
                        <span class="fw-semibold text-dark">{{ $ticket->customer->phone }}</span>
                    </div>
                @endif

                <hr class="my-1 text-light">

                <div class="d-flex justify-content-between small">
                    <span class="text-muted"><i class="bi bi-calendar-check me-1"></i> Member Since:</span>
                    <span class="fw-semibold text-dark">{{ $ticket->customer?->created_at ? $ticket->customer->created_at->format('M Y') : '&mdash;' }}</span>
                </div>
            </div>
        </div>

        <!-- Technical Ticket Details Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-dark display-font mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-secondary"></i>
                <span>Ticket Details</span>
            </h6>

            <div class="d-flex flex-column gap-3 small">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Ticket ID:</span>
                    <strong class="font-monospace text-dark">#{{ $ticket->id }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Category:</span>
                    <strong class="text-dark">{{ $ticket->category?->name ?? 'General' }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Created Timestamp:</span>
                    <strong class="text-dark">{{ $ticket->created_at->format('M d, Y - h:i A') }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Last Activity:</span>
                    <strong class="text-dark">{{ $ticket->updated_at->diffForHumans() }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
