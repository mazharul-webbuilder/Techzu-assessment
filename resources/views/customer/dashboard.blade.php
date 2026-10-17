@extends('layouts.app')

@section('title', 'Customer Dashboard')
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
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="modern-card p-4 p-md-5 position-relative mb-4">
            <div class="position-absolute top-0 start-0 w-100" style="height: 4px; background: var(--portal-gradient);"></div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bold mb-1 display-font text-dark">Welcome back, {{ Auth::guard('customer')->user()?->name }}!</h3>
                    <p class="text-muted small mb-0">Manage your support inquiries, monitor SLA response deadlines, and collaborate with technical agents.</p>
                </div>
                <div>
                    <a href="{{ route('customer.tickets.create') }}" class="btn btn-portal-primary d-inline-flex align-items-center gap-2">
                        <i class="bi bi-plus-lg"></i>
                        <span>Submit New Ticket</span>
                    </a>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <a href="{{ route('customer.tickets.index') }}" class="text-decoration-none">
                        <div class="p-4 rounded-3 border bg-light h-100 modern-card-hover">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5;">
                                    <i class="bi bi-inbox-fill"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">My Support Tickets</h5>
                                    <span class="text-muted small">View all past and active tickets</span>
                                </div>
                            </div>
                            <p class="text-secondary small mb-0 mt-3">Access conversation histories, track SLA countdown timers, and view resolution statuses.</p>
                        </div>
                    </a>
                </div>

                <div class="col-md-6">
                    <a href="{{ route('customer.tickets.create') }}" class="text-decoration-none">
                        <div class="p-4 rounded-3 border bg-light h-100 modern-card-hover">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                                    <i class="bi bi-plus-circle-fill"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-dark mb-0">Submit New Request</h5>
                                    <span class="text-muted small">Instant SLA assignment</span>
                                </div>
                            </div>
                            <p class="text-secondary small mb-0 mt-3">Choose from available categories and select urgency priority for automated support dispatch.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
