@extends('layouts.app')

@section('title', 'Create Support Ticket')
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
<div class="row justify-content-center py-2">
    <div class="col-lg-8 col-xl-7">
        <!-- Navigation & Heading -->
        <div class="mb-4">
            <a href="{{ route('customer.tickets.index') }}" class="text-decoration-none text-muted small fw-medium d-inline-flex align-items-center mb-2">
                <i class="bi bi-arrow-left me-1"></i> Back to My Tickets
            </a>
            <h3 class="fw-bold text-dark mb-1 display-font">Create Support Ticket</h3>
            <p class="text-muted small mb-0">Submit your request and our support team will respond within your target SLA window.</p>
        </div>

        <!-- Clean Form Card -->
        <div class="modern-card p-4 p-md-5">
            <form method="POST" action="{{ route('customer.tickets.store') }}" enctype="multipart/form-data">
                @csrf

                <!-- Subject -->
                <div class="mb-4">
                    <label for="subject" class="form-label fw-semibold small text-secondary">
                        Subject <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           name="subject"
                           id="subject"
                           class="form-control @error('subject') is-invalid @enderror"
                           value="{{ old('subject') }}"
                           placeholder="Brief summary of your request (e.g. Cannot access billing portal)"
                           required
                           maxlength="255">
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Category & Priority Row -->
                <div class="row g-3 mb-4">
                    <!-- Category -->
                    <div class="col-md-6">
                        <label for="category_id" class="form-label fw-semibold small text-secondary">
                            Category <span class="text-danger">*</span>
                        </label>
                        <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                            <option value="" disabled {{ old('category_id') ? '' : 'selected' }}>Select a category...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Priority (Compact Selection) -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small text-secondary d-block">
                            Priority <span class="text-danger">*</span>
                        </label>
                        <select name="priority" id="priority" class="form-select @error('priority') is-invalid @enderror" required>
                            @foreach($priorities as $priority)
                                @php
                                    $hours = $priority->slaMinutes() / 60;
                                @endphp
                                <option value="{{ $priority->value }}" {{ old('priority', 'medium') === $priority->value ? 'selected' : '' }}>
                                    {{ $priority->label() }} ({{ $hours }}h SLA)
                                </option>
                            @endforeach
                        </select>
                        @error('priority')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <label for="description" class="form-label fw-semibold small text-secondary">
                        Description <span class="text-danger">*</span>
                    </label>
                    <textarea name="description"
                              id="description"
                              rows="6"
                              class="form-control @error('description') is-invalid @enderror"
                              placeholder="Please provide full details, relevant context, or steps to reproduce the issue..."
                              required>{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text small text-muted">Include any error messages or reproduction steps to help us assist you faster.</div>
                </div>

                <!-- Attachments (Clean & Optional) -->
                <div class="mb-4">
                    <label for="attachments" class="form-label fw-semibold small text-secondary">
                        Attachments <span class="text-muted fw-normal">(Optional)</span>
                    </label>
                    <input type="file"
                           name="attachments[]"
                           id="attachments"
                           class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                           multiple
                           accept=".pdf,.png,.jpg,.jpeg,.txt,.docx">
                    @error('attachments')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @error('attachments.*')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text small text-muted">Upload up to 5 files (Max 5MB each). Allowed formats: PDF, PNG, JPG, TXT, DOCX.</div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-end align-items-center gap-3 pt-2 border-top">
                    <a href="{{ route('customer.tickets.index') }}" class="btn btn-light px-4 py-2 text-secondary fw-semibold rounded-3">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-portal-primary px-4 py-2 d-inline-flex align-items-center gap-2">
                        <i class="bi bi-send-fill"></i>
                        <span>Submit Ticket</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
