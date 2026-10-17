@extends('layouts.app')

@section('title', 'Ticket Work Queue')
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
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small text-muted mb-1">
                <li class="breadcrumb-item"><a href="{{ route('agent.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-headset me-1"></i>Agent</a></li>
                <li class="breadcrumb-item active text-teal fw-medium" aria-current="page">Ticket Queue</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1 display-font text-dark">Ticket Work Queue</h2>
        <p class="text-muted small mb-0">Showing assigned tickets and unassigned queue pool available for triage.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="POST" action="{{ route('agent.availability.toggle') }}" class="d-inline">
            @csrf
            @php $isAvailable = auth('agent')->user()?->is_available ?? false; @endphp
            <button type="submit" class="btn btn-sm {{ $isAvailable ? 'btn-outline-success bg-white' : 'btn-outline-secondary bg-white' }} px-3 py-2 rounded-3 shadow-sm d-inline-flex align-items-center gap-2" title="Click to toggle availability for auto-assignment">
                <span class="{{ $isAvailable ? 'live-pulse' : '' }}" style="{{ !$isAvailable ? 'width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; display: inline-block;' : '' }}"></span>
                <span class="small fw-semibold">{{ $isAvailable ? 'Available for Assignment' : 'Away (Paused)' }}</span>
                <i class="bi bi-arrow-repeat text-muted ms-1" style="font-size: 0.75rem;"></i>
            </button>
        </form>
    </div>
</div>

<div class="modern-card">
    <div class="p-3 px-4 border-bottom bg-white d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <!-- Queue Tabs -->
        <ul class="nav nav-pills gap-2">
            <li class="nav-item">
                <a class="nav-link btn-sm {{ $tab === 'all' || empty($tab) ? 'active bg-primary text-white' : 'text-dark bg-light border' }} rounded-3 py-1 px-3 d-inline-flex align-items-center gap-2" href="{{ route('agent.tickets.index', ['tab' => 'all']) }}">
                    <i class="bi bi-inbox-fill"></i>
                    <span class="small fw-semibold">All Queue</span>
                    <span class="badge {{ $tab === 'all' || empty($tab) ? 'bg-white text-primary' : 'bg-secondary text-white' }} rounded-pill">{{ $counts['all'] ?? $tickets->total() }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn-sm {{ $tab === 'my' ? 'active bg-primary text-white' : 'text-dark bg-light border' }} rounded-3 py-1 px-3 d-inline-flex align-items-center gap-2" href="{{ route('agent.tickets.index', ['tab' => 'my']) }}">
                    <i class="bi bi-person-badge-fill"></i>
                    <span class="small fw-semibold">My Assigned</span>
                    <span class="badge {{ $tab === 'my' ? 'bg-white text-primary' : 'bg-secondary text-white' }} rounded-pill">{{ $counts['my'] ?? 0 }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn-sm {{ $tab === 'unassigned' ? 'active bg-warning text-dark' : 'text-dark bg-light border' }} rounded-3 py-1 px-3 d-inline-flex align-items-center gap-2" href="{{ route('agent.tickets.index', ['tab' => 'unassigned']) }}">
                    <i class="bi bi-hand-index-thumb-fill {{ $tab === 'unassigned' ? 'text-dark' : 'text-warning' }}"></i>
                    <span class="small fw-semibold">Unassigned Pool</span>
                    <span class="badge {{ $tab === 'unassigned' ? 'bg-dark text-white' : 'bg-warning-subtle text-dark border border-warning' }} rounded-pill">{{ $counts['unassigned'] ?? 0 }}</span>
                    @if(($counts['unassigned'] ?? 0) > 0)
                        <span class="live-pulse" style="width: 6px; height: 6px;"></span>
                    @endif
                </a>
            </li>
        </ul>
        <span class="text-muted small">Showing {{ $tickets->total() }} tickets</span>
    </div>

    @include('tickets._ticket_table', ['tickets' => $tickets, 'portal' => 'agent'])
</div>
@endsection
