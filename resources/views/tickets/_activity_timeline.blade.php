<div class="modern-card">
    <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary fs-5"></i>
            <h6 class="fw-bold mb-0 text-dark display-font">Activity History &amp; Audit Trail</h6>
        </div>
        <span class="badge badge-soft-primary rounded-pill px-2 py-1">{{ $activities->count() }} events</span>
    </div>

    <div class="p-4">
        @if($activities->isEmpty())
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-clock mb-2 d-block fs-3"></i>
                No activity recorded yet.
            </div>
        @else
            <div class="position-relative ps-4" style="border-left: 2px solid #e2e8f0; margin-left: 10px;">
                @foreach($activities as $activity)
                    @php
                        $eventIcon = match($activity->event->value ?? '') {
                            'created' => ['icon' => 'bi-plus-circle-fill', 'color' => '#4f46e5', 'bg' => '#eef2ff'],
                            'reply_added' => ['icon' => 'bi-chat-dots-fill', 'color' => '#0284c7', 'bg' => '#f0f9ff'],
                            'status_changed' => ['icon' => 'bi-arrow-repeat', 'color' => '#d97706', 'bg' => '#fffbeb'],
                            'priority_changed' => ['icon' => 'bi-flag-fill', 'color' => '#e11d48', 'bg' => '#fff1f2'],
                            'assigned' => ['icon' => 'bi-person-check-fill', 'color' => '#059669', 'bg' => '#ecfdf5'],
                            'sla_warning' => ['icon' => 'bi-exclamation-triangle-fill', 'color' => '#d97706', 'bg' => '#fffbeb'],
                            'sla_breached' => ['icon' => 'bi-x-circle-fill', 'color' => '#dc2626', 'bg' => '#fef2f2'],
                            default => ['icon' => 'bi-check2', 'color' => '#64748b', 'bg' => '#f1f5f9'],
                        };
                    @endphp
                    <div class="mb-4 position-relative">
                        <span class="position-absolute d-flex align-items-center justify-content-center rounded-circle"
                              style="left: -33px; top: 0px; width: 26px; height: 26px; background: {{ $eventIcon['bg'] }}; color: {{ $eventIcon['color'] }}; border: 2px solid #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">
                            <i class="bi {{ $eventIcon['icon'] }}" style="font-size: 0.75rem;"></i>
                        </span>

                        <div class="bg-light p-3 rounded-3 border">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-1 mb-1">
                                <div class="fw-bold text-dark small">
                                    {{ $activity->description ?? $activity->event->label() }}
                                </div>
                                <span class="text-muted small" style="font-size: 0.75rem;">
                                    <i class="bi bi-clock me-1"></i>{{ $activity->created_at?->format('M d, Y h:i A') }}
                                </span>
                            </div>
                            <div class="text-muted small" style="font-size: 0.8rem;">
                                Initiated by:
                                @if($activity->actor)
                                    <span class="fw-semibold text-secondary">{{ $activity->actor->name }}</span>
                                    <span class="badge bg-white text-secondary border ms-1" style="font-size: 0.7rem;">{{ ucfirst($activity->actor_type) }}</span>
                                @else
                                    <span class="fw-semibold text-secondary">System Automation</span>
                                    <span class="badge bg-white text-secondary border ms-1" style="font-size: 0.7rem;">SLA Engine</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
