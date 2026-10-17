@extends('layouts.app')

@section('title', "Ticket {$ticket->ticket_number}")
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
    $isApproaching = $ticket->sla_warning_at && $ticket->sla_warning_at->isPast() && !$isBreached && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
@endphp

<!-- Breadcrumbs & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small text-muted mb-2">
            <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-house me-1"></i>Portal</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customer.tickets.index') }}" class="text-decoration-none text-muted">Tickets</a></li>
            <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">{{ $ticket->ticket_number }}</li>
        </ol>
    </nav>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="font-monospace fw-bold fs-4 text-dark">{{ $ticket->ticket_number }}</span>
                <span class="badge {{ $statusBadge }} rounded-pill px-3 py-1 fs-6">
                    {{ $ticket->status->label() }}
                </span>
                <span class="badge {{ $priorityBadge['class'] }} rounded-pill px-2 py-1">
                    <i class="bi {{ $priorityBadge['icon'] }} me-1"></i> {{ $ticket->priority->label() }}
                </span>
                @if($isBreached)
                    <span class="badge badge-soft-danger rounded-pill px-2 py-1">
                        <span class="live-pulse-danger me-1"></span> SLA Breached
                    </span>
                @elseif($isApproaching)
                    <span class="badge badge-soft-warning rounded-pill px-2 py-1">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> SLA Warning
                    </span>
                @endif
            </div>
            <h4 class="fw-bold mb-0 text-dark display-font">{{ $ticket->subject }}</h4>
        </div>
        <div>
            <a href="{{ route('customer.tickets.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 shadow-sm fw-medium">
                <i class="bi bi-arrow-left me-1"></i> Back to My Tickets
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main Ticket Thread & Details -->
    <div class="col-lg-8">
        <!-- Ticket Original Description Card -->
        <div class="modern-card mb-4 position-relative">
            <div class="p-3 px-4 border-bottom bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-circle" style="width: 28px; height: 28px; font-size: 0.75rem;">
                        {{ strtoupper(substr(Auth::guard('customer')->user()?->name ?? 'C', 0, 1)) }}
                    </div>
                    <div>
                        <span class="fw-bold text-dark small">{{ Auth::guard('customer')->user()?->name }}</span>
                        <span class="text-muted small">&bull; Submitted {{ $ticket->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <span class="badge bg-white text-secondary border small">
                    <i class="bi bi-tag me-1"></i>{{ $ticket->category->name }}
                </span>
            </div>
            <div class="p-4">
                <div class="text-secondary" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;">{{ $ticket->description }}</div>
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
                                $sizeKb = round($attachment->size_bytes / 1024, 0);
                            @endphp
                            <div class="p-2 px-3 rounded-3 border bg-white d-flex align-items-center gap-2">
                                <i class="bi {{ $isImg ? 'bi-file-earmark-image' : 'bi-file-earmark-text' }} text-secondary fs-5"></i>
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

        <!-- Activity & Event Audit Timeline -->
        <div class="modern-card">
            <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary fs-5"></i>
                    <h6 class="fw-bold mb-0 text-dark display-font">Ticket Activity &amp; Status Timeline</h6>
                </div>
                <span class="badge badge-soft-primary rounded-pill px-2 py-1">{{ $ticket->activities->count() }} events</span>
            </div>

            <div class="p-4">
                @if($ticket->activities->isEmpty())
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-clock mb-2 d-block fs-3"></i>
                        No activity recorded yet.
                    </div>
                @else
                    <div class="position-relative ps-4" style="border-left: 2px solid #e2e8f0; margin-left: 10px;">
                        @foreach($ticket->activities as $activity)
                            @php
                                $eventIcon = match($activity->event->value ?? '') {
                                    'created' => ['icon' => 'bi-plus-circle-fill', 'color' => '#4f46e5', 'bg' => '#eef2ff'],
                                    'reply_added' => ['icon' => 'bi-chat-dots-fill', 'color' => '#0284c7', 'bg' => '#f0f9ff'],
                                    'status_changed' => ['icon' => 'bi-arrow-repeat', 'color' => '#d97706', 'bg' => '#fffbeb'],
                                    'priority_changed' => ['icon' => 'bi-flag-fill', 'color' => '#e11d48', 'bg' => '#fff1f2'],
                                    'assigned' => ['icon' => 'bi-person-check-fill', 'color' => '#059669', 'bg' => '#ecfdf5'],
                                    'sla_warning' => ['icon' => 'bi-exclamation-triangle-fill', 'color' => '#d97706', 'bg' => '#fffbeb'],
                                    'sla_breached' => ['icon' => 'bi-x-circle-fill', 'color' => '#dc2626', 'bg' => '#fef2f2'],
                                    default => ['icon' => 'bi-check2', 'color' => '#64748b', 'bg' => '#f1f5f9'],
                                };
                            @endphp
                            <div class="mb-4 position-relative">
                                <!-- Marker -->
                                <span class="position-absolute d-flex align-items-center justify-content-center rounded-circle"
                                      style="left: -33px; top: 0px; width: 26px; height: 26px; background: {{ $eventIcon['bg'] }}; color: {{ $eventIcon['color'] }}; border: 2px solid #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">
                                    <i class="bi {{ $eventIcon['icon'] }}" style="font-size: 0.75rem;"></i>
                                </span>

                                <div class="bg-light p-3 rounded-3 border">
                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-1 mb-1">
                                        <div class="fw-bold text-dark small">
                                            {{ $activity->description ?? $activity->event->label() }}
                                        </div>
                                        <span class="text-muted small" style="font-size: 0.75rem;">
                                            <i class="bi bi-clock me-1"></i>{{ $activity->created_at?->format('M d, Y h:i A') }}
                                        </span>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.8rem;">
                                        Initiated by:
                                        @if($activity->actor)
                                            <span class="fw-semibold text-secondary">{{ $activity->actor->name }}</span>
                                            <span class="badge bg-white text-secondary border ms-1" style="font-size: 0.7rem;">{{ ucfirst($activity->actor_type) }}</span>
                                        @else
                                            <span class="fw-semibold text-secondary">System Automation</span>
                                            <span class="badge bg-white text-secondary border ms-1" style="font-size: 0.7rem;">SLA Engine</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Ticket Sidebar Meta -->
    <div class="col-lg-4">
        <!-- SLA Status Widget Card -->
        <div class="modern-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold text-dark display-font fs-6">SLA Countdown Target</span>
                @if($isBreached)
                    <span class="badge badge-soft-danger rounded-pill px-2 py-1"><i class="bi bi-x-circle me-1"></i>Breached</span>
                @elseif(in_array($ticket->status->value, ['resolved', 'closed']))
                    <span class="badge badge-soft-success rounded-pill px-2 py-1"><i class="bi bi-check2-circle me-1"></i>Met</span>
                @else
                    <span class="badge badge-soft-primary rounded-pill px-2 py-1"><i class="bi bi-hourglass-split me-1"></i>Tracking</span>
                @endif
            </div>

            <div class="p-3 rounded-3 bg-light border text-center mb-3">
                <div class="text-muted small text-uppercase fw-bold letter-spacing-1 mb-1">Resolution Deadline</div>
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
                    <span>Priority SLA Duration:</span>
                    <strong class="text-dark">{{ $ticket->priority->slaMinutes() / 60 }} Business Hours</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Warning Threshold (80%):</span>
                    <strong class="text-dark">{{ $ticket->sla_warning_at ? $ticket->sla_warning_at->format('M d, H:i') : '&mdash;' }}</strong>
                </div>
            </div>
        </div>

        <!-- Ticket Overview Properties Card -->
        <div class="modern-card p-4 mb-4">
            <h6 class="fw-bold text-dark display-font mb-3">Ticket Information</h6>

            <div class="d-flex flex-column gap-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Assigned Support Agent</span>
                    <div class="mt-1">
                        @if($ticket->assignedAgent)
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width: 26px; height: 26px; font-size: 0.7rem; background: var(--portal-gradient);">
                                    {{ strtoupper(substr($ticket->assignedAgent->name, 0, 1)) }}
                                </div>
                                <span class="fw-semibold text-dark small">{{ $ticket->assignedAgent->name }}</span>
                            </div>
                        @else
                            <span class="badge badge-soft-secondary rounded-pill px-2 py-1">
                                <i class="bi bi-hourglass me-1"></i> Unassigned (In Triage Queue)
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
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Created Timestamp</span>
                    <span class="text-secondary small mt-1 d-block">{{ $ticket->created_at->format('M d, Y - h:i A') }}</span>
                </div>

                <hr class="my-1 text-light">

                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Last Updated</span>
                    <span class="text-secondary small mt-1 d-block">{{ $ticket->updated_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
