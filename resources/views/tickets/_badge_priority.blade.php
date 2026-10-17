@php
    $priorityVal = $priority instanceof \App\Enums\Priority ? $priority->value : $priority;
    $meta = match($priorityVal) {
        'critical' => ['class' => 'badge-soft-danger', 'icon' => 'bi-lightning-charge-fill'],
        'high' => ['class' => 'badge-soft-warning', 'icon' => 'bi-exclamation-circle-fill'],
        'medium' => ['class' => 'badge-soft-info', 'icon' => 'bi-dash-circle-fill'],
        'low' => ['class' => 'badge-soft-success', 'icon' => 'bi-info-circle-fill'],
        default => ['class' => 'badge-soft-secondary', 'icon' => 'bi-flag-fill'],
    };
    $label = $priority instanceof \App\Enums\Priority ? $priority->label() : ucfirst($priorityVal);
@endphp
<span class="badge {{ $meta['class'] }} rounded-pill px-2 py-1">
    <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $label }}
</span>
