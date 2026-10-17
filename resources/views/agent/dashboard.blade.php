@extends('layouts.app')

@section('title', 'Agent Dashboard')
@section('nav_bg', 'bg-warning bg-opacity-75 text-dark')
@section('portal_name', 'Support Agent Portal')
@section('user_name', Auth::guard('agent')->user()?->name ?? 'Agent')

@section('logout_button')
<form method="POST" action="{{ route('agent.logout') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-sm btn-dark">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
    </button>
</form>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <h4 class="card-title fw-bold text-dark">Welcome, {{ Auth::guard('agent')->user()?->name }}!</h4>
        <p class="text-muted">You are successfully authenticated via the <code>agent</code> guard.</p>
        <div class="alert alert-warning border-0 rounded-3 mb-0">
            <i class="bi bi-headset me-2"></i>
            This is the placeholder Agent Portal dashboard. Ticket claims, queues, and conversations will be connected in Phase 6-8.
        </div>
    </div>
</div>
@endsection
