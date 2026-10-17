@extends('layouts.app')

@section('title', 'All Support Tickets')
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
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small text-muted mb-1">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-shield-lock me-1"></i>Admin</a></li>
                <li class="breadcrumb-item active text-danger fw-medium" aria-current="page">Tickets</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1 display-font text-dark">All Support Tickets</h2>
        <p class="text-muted small mb-0">System-wide global overview of tickets across all customer accounts and agents.</p>
    </div>
</div>

<div class="modern-card">
    <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <h5 class="fw-bold mb-0 text-dark display-font fs-6">Global Tickets</h5>
            <span class="badge badge-soft-danger rounded-pill px-2 py-1">{{ $tickets->total() }} total</span>
        </div>
    </div>

    @include('tickets._ticket_table', ['tickets' => $tickets, 'portal' => 'admin'])
</div>
@endsection
