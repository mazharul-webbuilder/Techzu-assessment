@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('nav_bg', 'bg-danger')
@section('portal_name', 'System Admin Portal')
@section('user_name', Auth::guard('admin')->user()?->name ?? 'Administrator')

@section('logout_button')
<form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-light">
        <i class="bi bi-box-arrow-right me-1"></i> Logout
    </button>
</form>
@endsection

@section('content')
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <h4 class="card-title fw-bold text-dark">Welcome, {{ Auth::guard('admin')->user()?->name }}!</h4>
        <p class="text-muted">You are successfully authenticated via the <code>admin</code> guard.</p>
        <div class="alert alert-danger border-0 rounded-3 mb-0">
            <i class="bi bi-shield-lock me-2"></i>
            This is the placeholder Admin Portal dashboard. Metrics, SLA monitoring, and CSV imports will be connected in Phase 13-15.
        </div>
    </div>
</div>
@endsection
