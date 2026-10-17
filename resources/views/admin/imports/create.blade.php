@extends('layouts.app')

@section('title', 'Upload Ticket CSV - Admin Console')
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
<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <!-- Breadcrumb & Header -->
        <div class="mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small text-muted mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-shield-lock me-1"></i>Admin</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.imports.index') }}" class="text-decoration-none text-muted">CSV Imports</a></li>
                    <li class="breadcrumb-item active text-danger fw-medium" aria-current="page">Upload</li>
                </ol>
            </nav>
            <h2 class="fw-bold mb-1 display-font text-dark">Import Support Tickets</h2>
            <p class="text-muted small mb-0">Upload a CSV file containing ticket records. Processing runs asynchronously in the background.</p>
        </div>

        @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <span class="fw-bold">Validation Error</span>
            </div>
            <ul class="mb-0 small ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Upload Card -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 p-md-5">
                <form action="{{ route('admin.imports.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-4">
                        <label for="file" class="form-label fw-bold text-dark">Select CSV File <span class="text-danger">*</span></label>
                        <div class="p-4 border border-2 border-dashed rounded-4 text-center bg-light position-relative">
                            <i class="bi bi-file-earmark-spreadsheet display-4 text-primary d-block mb-2"></i>
                            <h6 class="fw-semibold text-dark mb-1">Choose a CSV file or drag and drop</h6>
                            <p class="text-muted small mb-3">Accepted format: <code>.csv</code> or <code>.txt</code> (Max size: 10 MB)</p>
                            
                            <input type="file" name="file" id="file" class="form-control" accept=".csv,text/csv,text/plain" required>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="{{ route('admin.imports.index') }}" class="btn btn-outline-secondary px-4 rounded-3">
                            <i class="bi bi-arrow-left me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-portal-primary px-4 rounded-3 d-inline-flex align-items-center gap-2">
                            <i class="bi bi-cloud-arrow-up-fill"></i>
                            <span>Upload & Start Processing</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- CSV Specification Guidelines -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h6 class="fw-bold mb-0 text-dark display-font">
                    <i class="bi bi-info-circle-fill me-2 text-info"></i>Expected CSV Schema & Requirements
                </h6>
            </div>
            <div class="card-body p-4">
                <p class="small text-muted mb-3">
                    The CSV file must contain a header row with the following column names (order is flexible):
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Column Header</th>
                                <th>Required?</th>
                                <th>Description / Allowed Values</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>external_id</code></td>
                                <td><span class="badge bg-secondary-subtle text-secondary">Optional</span></td>
                                <td>Unique identifier used for deduplication. Repeated IDs are safely skipped.</td>
                            </tr>
                            <tr>
                                <td><code>customer_email</code></td>
                                <td><span class="badge bg-danger-subtle text-danger">Required</span></td>
                                <td>Valid email address. Customer will be created automatically if missing.</td>
                            </tr>
                            <tr>
                                <td><code>customer_name</code></td>
                                <td><span class="badge bg-secondary-subtle text-secondary">Optional</span></td>
                                <td>Display name for the customer. Defaults to the username portion of email.</td>
                            </tr>
                            <tr>
                                <td><code>subject</code></td>
                                <td><span class="badge bg-danger-subtle text-danger">Required</span></td>
                                <td>Ticket summary/title (max 255 characters).</td>
                            </tr>
                            <tr>
                                <td><code>description</code></td>
                                <td><span class="badge bg-danger-subtle text-danger">Required</span></td>
                                <td>Detailed ticket problem narrative or inquiry body.</td>
                            </tr>
                            <tr>
                                <td><code>priority</code></td>
                                <td><span class="badge bg-danger-subtle text-danger">Required</span></td>
                                <td>One of: <code>low</code>, <code>medium</code>, <code>high</code>, <code>critical</code></td>
                            </tr>
                            <tr>
                                <td><code>category</code></td>
                                <td><span class="badge bg-danger-subtle text-danger">Required</span></td>
                                <td>Category name (e.g. <code>Billing</code>, <code>Technical</code>). Created if missing.</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td><span class="badge bg-danger-subtle text-danger">Required</span></td>
                                <td>One of: <code>open</code>, <code>in_progress</code>, <code>waiting_for_customer</code>, <code>resolved</code>, <code>closed</code></td>
                            </tr>
                            <tr>
                                <td><code>created_at</code></td>
                                <td><span class="badge bg-danger-subtle text-danger">Required</span></td>
                                <td>Timestamp (e.g. <code>2026-01-15 10:30:00</code>). SLA deadlines are calculated from this date.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-light p-3 rounded-3 small">
                    <div class="fw-semibold text-dark mb-1"><i class="bi bi-filetype-csv me-1"></i>Sample Header & Row:</div>
                    <code class="d-block text-break user-select-all" style="font-size: 0.78rem;">
                        external_id,customer_email,customer_name,subject,description,priority,category,status,created_at<br>
                        LEGACY-101,sarah@example.com,Sarah Connor,Database connection error,Unable to connect to primary DB,critical,Infrastructure,open,2026-03-01 09:15:00
                    </code>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
