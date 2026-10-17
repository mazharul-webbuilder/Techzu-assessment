@php
    $statusClass = match($status instanceof \App\Enums\TicketStatus ? $status->value : $status) {
        'open' => 'badge-soft-primary',
        'in_progress' => 'badge-soft-warning',
        'waiting_for_customer' => 'badge-soft-info',
        'resolved' => 'badge-soft-success',
        'closed' => 'badge-soft-secondary',
        default => 'badge-soft-secondary',
    };
    $label = $status instanceof \App\Enums\TicketStatus ? $status->label() : ucfirst(str_replace('_', ' ', $status));
@endphp
<span class="badge {{ $statusClass }} rounded-pill px-2 py-1">
    {{ $label }}
</span>
