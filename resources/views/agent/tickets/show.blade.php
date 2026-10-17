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
    $isBreached = $ticket->sla_due_at && $ticket->sla_due_at->isPast() && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
    $isApproaching = $ticket->sla_warning_at && $ticket->sla_warning_at->isPast() && !$isBreached && in_array($ticket->status->value, ['open', 'in_progress', 'waiting_for_customer']);
@endphp

<!-- Breadcrumbs & Header -->
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small text-muted mb-2">
            <li class="breadcrumb-item"><a href="{{ route('agent.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-headset me-1"></i>Agent</a></li>
            <li class="breadcrumb-item"><a href="{{ route('agent.tickets.index') }}" class="text-decoration-none text-muted">Ticket Queue</a></li>
            <li class="breadcrumb-item active text-teal fw-medium" aria-current="page">{{ $ticket->ticket_number }}</li>
        </ol>
    </nav>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="font-monospace fw-bold fs-4 text-dark">{{ $ticket->ticket_number }}</span>
                @include('tickets._badge_status', ['status' => $ticket->status])
                @include('tickets._badge_priority', ['priority' => $ticket->priority])

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
        <div class="d-flex align-items-center gap-2">
            @if($ticket->assigned_agent_id === null)
                <form method="POST" action="{{ route('agent.tickets.claim', $ticket) }}">
                    @csrf
                    <button type="submit" class="btn btn-portal-primary btn-sm px-3 py-2 rounded-3 shadow-sm fw-semibold d-inline-flex align-items-center gap-1">
                        <i class="bi bi-hand-index-thumb"></i>
                        <span>Claim This Ticket</span>
                    </button>
                </form>
            @endif
            <a href="{{ route('agent.tickets.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 shadow-sm fw-medium">
                <i class="bi bi-arrow-left me-1"></i> Back to Queue
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main Ticket Thread & Activity -->
    <div class="col-lg-8">
        <!-- Customer Request Card -->
        <div class="modern-card mb-4 position-relative">
            <div class="p-3 px-4 border-bottom bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-circle" style="width: 28px; height: 28px; font-size: 0.75rem; background: var(--portal-gradient);">
                        {{ strtoupper(substr($ticket->customer?->name ?? 'C', 0, 1)) }}
                    </div>
                    <div>
                        <span class="fw-bold text-dark small">{{ $ticket->customer?->name ?? 'Customer' }}</span>
                        <span class="text-muted small">&bull; Submitted {{ $ticket->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                <span class="badge bg-white text-secondary border small">
                    <i class="bi bi-tag me-1"></i>{{ $ticket->category?->name ?? 'General' }}
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
                                $sizeKb = round($attachment->size_bytes / 1024, 0);
                            @endphp
                            <div class="p-2 px-3 rounded-3 border bg-white d-flex align-items-center gap-2">
                                <i class="bi bi-file-earmark-text text-secondary fs-5"></i>
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

        <!-- Replies & Internal Notes Thread -->
        @include('tickets._replies_thread', ['replies' => $ticket->replies])

        <!-- Activity Timeline -->
        @include('tickets._activity_timeline', ['activities' => $ticket->activities])
    </div>

    <!-- Right Sidebar Meta -->
    <div class="col-lg-4">
        <!-- SLA Target Monitor -->
        <div class="modern-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="fw-bold text-dark display-font fs-6">SLA Countdown Target</span>
                @if($isBreached)
                    <span class="badge badge-soft-danger rounded-pill px-2 py-1">Breached</span>
                @elseif(in_array($ticket->status->value, ['resolved', 'closed']))
                    <span class="badge badge-soft-success rounded-pill px-2 py-1">Resolved</span>
                @else
                    <span class="badge badge-soft-primary rounded-pill px-2 py-1">Tracking</span>
                @endif
            </div>

            <div class="p-3 rounded-3 bg-light border text-center mb-3">
                <div class="text-muted small text-uppercase fw-bold letter-spacing-1 mb-1">Resolution Deadline</div>
                @if($ticket->sla_due_at)
                    <div class="display-font fw-bold fs-4 {{ $isBreached ? 'text-danger' : 'text-primary' }}">
                        {{ $ticket->sla_due_at->diffForHumans() }}
                    </div>
                    <div class="small text-muted mt-1">{{ $ticket->sla_due_at->format('M d, Y - h:i A') }}</div>
                @else
                    <div class="text-muted">&mdash;</div>
                @endif
            </div>

            <div class="small text-muted d-flex flex-column gap-2">
                <div class="d-flex justify-content-between">
                    <span>SLA Window:</span>
                    <strong class="text-dark">{{ $ticket->priority->slaMinutes() / 60 }} Business Hours</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Warning At (80%):</span>
                    <strong class="text-dark">{{ $ticket->sla_warning_at ? $ticket->sla_warning_at->format('M d, H:i') : '&mdash;' }}</strong>
                </div>
            </div>
        </div>

        <!-- Customer & Assignment Info -->
        <div class="modern-card p-4 mb-4">
            <h6 class="fw-bold text-dark display-font mb-3">Ticket Information</h6>

            <div class="d-flex flex-column gap-3">
                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Customer</span>
                    <div class="fw-bold text-dark small mt-1">{{ $ticket->customer?->name }}</div>
                    <div class="text-muted small">{{ $ticket->customer?->email }}</div>
                    @if($ticket->customer?->phone)
                        <div class="text-muted small">{{ $ticket->customer->phone }}</div>
                    @endif
                </div>

                <hr class="my-1 text-light">

                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Assigned Agent</span>
                    <div class="mt-1">
                        @if($ticket->assignedAgent)
                            <span class="fw-semibold text-dark small">{{ $ticket->assignedAgent->name }}</span>
                        @else
                            <span class="badge badge-soft-warning rounded-pill px-2 py-1">Unassigned</span>
                        @endif
                    </div>
                </div>

                <hr class="my-1 text-light">

                <div>
                    <span class="text-muted small text-uppercase fw-semibold d-block" style="font-size: 0.72rem; letter-spacing: 0.05em;">Category</span>
                    <span class="fw-semibold text-dark small mt-1 d-block">{{ $ticket->category?->name ?? 'General' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
