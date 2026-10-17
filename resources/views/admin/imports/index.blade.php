@extends('layouts.app')

@section('title', 'Ticket Batch Imports - Admin Console')
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
                <li class="breadcrumb-item active text-danger fw-medium" aria-current="page">CSV Imports</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1 display-font text-dark">Ticket Batch Imports</h2>
        <p class="text-muted small mb-0">Asynchronous streaming CSV imports with flat memory consumption and idempotency deduplication.</p>
    </div>
    <div>
        <a href="{{ route('admin.imports.create') }}" class="btn btn-portal-primary d-inline-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-upload"></i>
            <span>Upload New CSV</span>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <h5 class="fw-bold mb-0 text-dark display-font" style="font-size: 1.05rem;">
            <i class="bi bi-clock-history me-2 text-primary"></i>Import Job History
        </h5>
        <span class="badge bg-light text-muted border px-2 py-1 small fw-normal">
            Total Imports: {{ $imports->total() }}
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-modern align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col" style="width: 80px;">Job ID</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-center">Total Rows</th>
                    <th scope="col" class="text-center">Processed</th>
                    <th scope="col" class="text-center">Skipped (Dupes)</th>
                    <th scope="col" class="text-center">Failed</th>
                    <th scope="col">Triggered By</th>
                    <th scope="col">Date / Time</th>
                    <th scope="col" class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($imports as $import)
                <tr>
                    <td class="fw-bold text-muted">#{{ $import->id }}</td>
                    <td>
                        @if($import->status === 'completed')
                            <span class="badge badge-soft-success px-2 py-1 rounded-pill">
                                <i class="bi bi-check-circle-fill me-1"></i>Completed
                            </span>
                        @elseif($import->status === 'processing')
                            <span class="badge badge-soft-primary px-2 py-1 rounded-pill">
                                <span class="spinner-border spinner-border-sm me-1" style="width: 0.75rem; height: 0.75rem;"></span>Processing
                            </span>
                        @elseif($import->status === 'pending')
                            <span class="badge badge-soft-warning px-2 py-1 rounded-pill">
                                <i class="bi bi-clock me-1"></i>Pending
                            </span>
                        @else
                            <span class="badge badge-soft-danger px-2 py-1 rounded-pill">
                                <i class="bi bi-x-circle-fill me-1"></i>Failed
                            </span>
                        @endif
                    </td>
                    <td class="text-center fw-semibold text-dark">{{ number_format($import->total_rows) }}</td>
                    <td class="text-center">
                        <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill fw-semibold">
                            {{ number_format($import->processed_rows) }}
                        </span>
                    </td>
                    <td class="text-center">
                        @if($import->skipped_duplicates > 0)
                            <span class="badge bg-warning-subtle text-warning-emphasis px-2 py-1 rounded-pill fw-semibold">
                                {{ number_format($import->skipped_duplicates) }}
                            </span>
                        @else
                            <span class="text-muted small">0</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($import->failed_rows > 0)
                            <span class="badge bg-danger-subtle text-danger px-2 py-1 rounded-pill fw-semibold">
                                {{ number_format($import->failed_rows) }}
                            </span>
                        @else
                            <span class="text-muted small">0</span>
                        @endif
                    </td>
                    <td>
                        <div class="small fw-medium text-dark">{{ $import->admin?->name ?? 'System' }}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ $import->admin?->email }}</div>
                    </td>
                    <td>
                        <div class="small text-dark">{{ $import->created_at->format('M d, Y') }}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">{{ $import->created_at->format('H:i:s') }}</div>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="{{ route('admin.imports.show', $import) }}" class="btn btn-sm btn-outline-secondary rounded-2 px-2 py-1" title="View Progress & Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if($import->failed_rows > 0 && $import->error_report_path)
                                <a href="{{ route('admin.imports.errors', $import) }}" class="btn btn-sm btn-outline-danger rounded-2 px-2 py-1" title="Download Error CSV">
                                    <i class="bi bi-file-earmark-x"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-cloud-arrow-up display-5 d-block text-secondary mb-3 opacity-50"></i>
                        <h6 class="fw-semibold">No ticket imports yet</h6>
                        <p class="small text-muted mb-3">Upload a CSV file to import legacy or bulk tickets with automatic customer & category matching.</p>
                        <a href="{{ route('admin.imports.create') }}" class="btn btn-sm btn-portal-primary">
                            <i class="bi bi-upload me-1"></i> Start First Import
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($imports->hasPages())
    <div class="card-footer bg-white border-top py-3 px-4">
        {{ $imports->links() }}
    </div>
    @endif
</div>
@endsection
