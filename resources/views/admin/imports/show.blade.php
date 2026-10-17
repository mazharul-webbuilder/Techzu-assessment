@extends('layouts.app')

@section('title', "Import #{$import->id} Details - Admin Console")
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

@section('extra_css')
@if(in_array($import->status, ['pending', 'processing']))
<meta http-equiv="refresh" content="3">
@endif
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
        <!-- Header & Breadcrumbs -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small text-muted mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-shield-lock me-1"></i>Admin</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.imports.index') }}" class="text-decoration-none text-muted">CSV Imports</a></li>
                        <li class="breadcrumb-item active text-danger fw-medium" aria-current="page">Job #{{ $import->id }}</li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center gap-3">
                    <h2 class="fw-bold mb-0 display-font text-dark">Batch Import #{{ $import->id }}</h2>
                    @if($import->status === 'completed')
                        <span class="badge badge-soft-success px-3 py-1 rounded-pill">
                            <i class="bi bi-check-circle-fill me-1"></i>Completed
                        </span>
                    @elseif($import->status === 'processing')
                        <span class="badge badge-soft-primary px-3 py-1 rounded-pill">
                            <span class="spinner-border spinner-border-sm me-1" style="width: 0.75rem; height: 0.75rem;"></span>Processing
                        </span>
                    @elseif($import->status === 'pending')
                        <span class="badge badge-soft-warning px-3 py-1 rounded-pill">
                            <i class="bi bi-clock me-1"></i>Pending Queue
                        </span>
                    @else
                        <span class="badge badge-soft-danger px-3 py-1 rounded-pill">
                            <i class="bi bi-x-circle-fill me-1"></i>Failed
                        </span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.imports.index') }}" class="btn btn-outline-secondary px-3 rounded-3">
                    <i class="bi bi-arrow-left me-1"></i> All Imports
                </a>
                @if(in_array($import->status, ['pending', 'processing']))
                <button onclick="window.location.reload();" class="btn btn-portal-primary px-3 rounded-3">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
                @endif
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @php
            $handledRows = $import->processed_rows + $import->failed_rows + $import->skipped_duplicates;
            $percent = ($import->total_rows > 0)
                ? min(100, (int) round(($handledRows / $import->total_rows) * 100))
                : ($import->status === 'completed' ? 100 : 0);
        @endphp

        <!-- Progress Overview Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-dark">Processing Progress</span>
                    <span class="fw-bold display-font text-primary">{{ $percent }}%</span>
                </div>
                <div class="progress mb-3" style="height: 12px; border-radius: 8px;">
                    <div class="progress-bar progress-bar-striped {{ in_array($import->status, ['pending', 'processing']) ? 'progress-bar-animated bg-primary' : ($import->status === 'failed' ? 'bg-danger' : 'bg-success') }}" 
                         role="progressbar" 
                         style="width: {{ $percent }}%;" 
                         aria-valuenow="{{ $percent }}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                    </div>
                </div>

                @if(in_array($import->status, ['pending', 'processing']))
                <div class="text-center text-muted small">
                    <span class="spinner-border spinner-border-sm me-1 text-primary"></span>
                    Processing streamed rows in background... page automatically refreshes every 3 seconds.
                </div>
                @endif
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-3 text-center">
                        <div class="text-muted small fw-semibold text-uppercase mb-1" style="font-size: 0.72rem;">Total Rows</div>
                        <div class="display-font fw-bold fs-3 text-dark">{{ number_format($import->total_rows) }}</div>
                        <div class="text-muted small" style="font-size: 0.75rem;">Parsed in file</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="background: #f0fdf4;">
                    <div class="card-body p-3 text-center">
                        <div class="text-success small fw-semibold text-uppercase mb-1" style="font-size: 0.72rem;">Processed</div>
                        <div class="display-font fw-bold fs-3 text-success">{{ number_format($import->processed_rows) }}</div>
                        <div class="text-success small" style="font-size: 0.75rem;">Tickets created</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="background: #fffbeb;">
                    <div class="card-body p-3 text-center">
                        <div class="text-warning-emphasis small fw-semibold text-uppercase mb-1" style="font-size: 0.72rem;">Duplicates</div>
                        <div class="display-font fw-bold fs-3 text-warning-emphasis">{{ number_format($import->skipped_duplicates) }}</div>
                        <div class="text-warning-emphasis small" style="font-size: 0.75rem;">Safely skipped</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100" style="background: {{ $import->failed_rows > 0 ? '#fef2f2' : '#ffffff' }};">
                    <div class="card-body p-3 text-center">
                        <div class="{{ $import->failed_rows > 0 ? 'text-danger' : 'text-muted' }} small fw-semibold text-uppercase mb-1" style="font-size: 0.72rem;">Failed Rows</div>
                        <div class="display-font fw-bold fs-3 {{ $import->failed_rows > 0 ? 'text-danger' : 'text-dark' }}">{{ number_format($import->failed_rows) }}</div>
                        <div class="{{ $import->failed_rows > 0 ? 'text-danger' : 'text-muted' }} small" style="font-size: 0.75rem;">Validation errors</div>
                    </div>
                </div>
            </div>
        </div>

        @if($import->failed_rows > 0 && $import->error_report_path)
        <!-- Error Report Action Box -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: #fff1f2; border: 1px solid #fecdd3 !important;">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="fw-bold text-danger mb-1 display-font">
                        <i class="bi bi-exclamation-octagon-fill me-2"></i>Validation Failures Detected
                    </h5>
                    <p class="text-danger small mb-0 opacity-75">
                        {{ number_format($import->failed_rows) }} rows failed validation. Download the detailed CSV error report containing row numbers and exact reasons.
                    </p>
                </div>
                <a href="{{ route('admin.imports.errors', $import) }}" class="btn btn-danger px-4 py-2 rounded-3 text-nowrap d-inline-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-file-earmark-arrow-down-fill"></i>
                    <span>Download Error Report (.csv)</span>
                </a>
            </div>
        </div>
        @endif

        <!-- Job Metadata & Execution Details -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h6 class="fw-bold mb-0 text-dark display-font">
                    <i class="bi bi-info-square-fill me-2 text-primary"></i>Execution Details
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Triggered By</label>
                        <span class="fw-semibold text-dark">{{ $import->admin?->name ?? 'System' }} ({{ $import->admin?->email ?? 'N/A' }})</span>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="text-muted small d-block">Uploaded File Storage Path</label>
                        <code class="text-break" style="font-size: 0.8rem;">{{ $import->file_path }}</code>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="text-muted small d-block">Submitted At</label>
                        <span class="text-dark small">{{ $import->created_at->format('Y-m-d H:i:s') }}</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="text-muted small d-block">Processing Started At</label>
                        <span class="text-dark small">{{ $import->started_at ? $import->started_at->format('Y-m-d H:i:s') : 'Pending' }}</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="text-muted small d-block">Processing Completed At</label>
                        <span class="text-dark small">{{ $import->finished_at ? $import->finished_at->format('Y-m-d H:i:s') : 'In progress...' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
