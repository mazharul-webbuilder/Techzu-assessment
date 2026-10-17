@extends('layouts.app')

@section('title', 'Customer Dashboard')
@section('nav_bg', 'bg-primary')
@section('portal_name', 'Customer Portal')
@section('user_name', Auth::guard('customer')->user()?->name ?? 'Customer')

@section('logout_button')
<form method="POST" action="{{ route('customer.logout') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-light">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
    </button>
</form>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <h4 class="card-title fw-bold text-dark">Welcome, {{ Auth::guard('customer')->user()?->name }}!</h4>
        <p class="text-muted">You are successfully authenticated via the <code>customer</code> guard.</p>
        <div class="alert alert-info border-0 rounded-3 mb-0">
            <i class="bi bi-info-circle me-2"></i>
            This is the placeholder Customer Portal dashboard. Ticket creation and conversation history views will be connected in Phase 5.
        </div>
    </div>
</div>
@endsection
