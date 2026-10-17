@extends('layouts.auth')

@section('title', 'Support Agent Portal Login')

@section('content')
<div class="auth-header">
    <span class="portal-badge portal-agent"><i class="bi bi-headset me-1"></i> Support Agent Portal</span>
    <h3 class="fw-bold text-dark mb-1">Agent Sign In</h3>
    <p class="text-muted small">Access assigned tickets and manage customer conversations</p>
</div>

<div class="auth-body">
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
            <i class="bi bi-check-circle me-1"></i> {{ session('status') }}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('agent.login.submit') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label small fw-semibold text-secondary">Work Email Address</label>
            <div class="input-group">
                <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email"
                       class="form-control border-start-0 @error('email') is-invalid @enderror"
                       value="{{ old('email', 'agent1@example.com') }}" required autofocus placeholder="agent@example.com">
            </div>
            @error('email')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label small fw-semibold text-secondary">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control border-start-0 @error('password') is-invalid @enderror"
                       value="password" required placeholder="••••••••">
            </div>
            @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label small text-muted" for="remember">
                    Remember me
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-portal-agent w-100 py-2 fw-semibold shadow-sm">
            Sign In to Agent Portal <i class="bi bi-arrow-right ms-1"></i>
        </button>
    </form>

    <div class="demo-credential-box">
        <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i> Seeded Agent Demo:</div>
        <div>Email: <code>agent1@example.com</code></div>
        <div>Password: <code>password</code></div>
    </div>

    <hr class="my-4 text-muted">

    <div class="portal-switchers text-center">
        <div class="text-muted small mb-2">Switch to other portals:</div>
        <div class="d-flex justify-content-center gap-3">
            <a href="{{ route('customer.login') }}"><i class="bi bi-person me-1 text-primary"></i> Customer Portal</a>
            <span class="text-muted">|</span>
            <a href="{{ route('admin.login') }}"><i class="bi bi-shield-lock me-1 text-danger"></i> Admin Portal</a>
        </div>
    </div>
</div>
@endsection
