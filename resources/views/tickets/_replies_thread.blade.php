<div class="modern-card mb-4">
    <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-chat-dots-fill text-primary fs-5"></i>
            <h6 class="fw-bold mb-0 text-dark display-font">Conversation &amp; Updates</h6>
        </div>
        <span class="badge badge-soft-secondary rounded-pill px-2 py-1">{{ $replies->count() }} messages</span>
    </div>

    <div class="p-4">
        @if($replies->isEmpty())
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-chat-square-text mb-2 d-block fs-3"></i>
                No replies or notes posted yet.
            </div>
        @else
            <div class="d-flex flex-column gap-3">
                @foreach($replies as $reply)
                    @php
                        $isInternal = (bool) $reply->is_internal;
                        $authorName = $reply->author?->name ?? 'User';
                        $authorInitial = strtoupper(substr($authorName, 0, 1));
                    @endphp
                    <div class="p-3 rounded-3 border {{ $isInternal ? 'bg-warning-subtle border-warning' : 'bg-light border-light-subtle' }}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width: 28px; height: 28px; font-size: 0.72rem; background: {{ $isInternal ? '#d97706' : 'var(--portal-gradient)' }};">
                                    {{ $authorInitial }}
                                </div>
                                <span class="fw-bold text-dark small">{{ $authorName }}</span>
                                <span class="badge {{ $isInternal ? 'badge-soft-warning' : 'badge-soft-secondary' }} small">
                                    @if($isInternal)
                                        <i class="bi bi-lock-fill me-1"></i>Internal Staff Note
                                    @else
                                        {{ ucfirst($reply->author_type) }}
                                    @endif
                                </span>
                            </div>
                            <span class="text-muted small" style="font-size: 0.75rem;">
                                <i class="bi bi-clock me-1"></i>{{ $reply->created_at->format('M d, Y h:i A') }}
                            </span>
                        </div>
                        <div class="text-secondary ps-4" style="white-space: pre-wrap; font-size: 0.92rem;">{{ $reply->body }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
