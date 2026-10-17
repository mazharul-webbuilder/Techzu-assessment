<div class="modern-card mb-4">
    <div class="p-3 px-4 border-bottom bg-white d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-chat-dots-fill text-primary fs-5"></i>
            <h6 class="fw-bold mb-0 text-dark display-font">Conversation &amp; Updates</h6>
        </div>
        <span class="badge badge-soft-secondary rounded-pill px-2 py-1">{{ $replies->count() }} messages</span>
    </div>

    <!-- Replies Message Stream -->
    <div class="p-4">
        @if($replies->isEmpty())
            <div class="text-center py-4 text-muted small">
                <i class="bi bi-chat-square-text mb-2 d-block fs-3 text-muted"></i>
                No replies or notes posted yet.
            </div>
        @else
            <div class="d-flex flex-column gap-3 mb-4">
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
                                        {{ ucfirst($reply->author_type ?? 'User') }}
                                    @endif
                                </span>
                            </div>
                            <span class="text-muted small" style="font-size: 0.75rem;">
                                <i class="bi bi-clock me-1"></i>{{ $reply->created_at->format('M d, Y h:i A') }}
                            </span>
                        </div>
                        <div class="text-secondary ps-4" style="white-space: pre-wrap; font-size: 0.92rem;">{{ $reply->body }}</div>

                        @if($reply->attachments && $reply->attachments->isNotEmpty())
                            <div class="mt-2 ps-4 d-flex flex-wrap gap-2">
                                @foreach($reply->attachments as $attachment)
                                    @php $sizeKb = round($attachment->size_bytes / 1024, 0); @endphp
                                    <a href="{{ route('attachments.download', $attachment) }}" class="btn btn-sm btn-white bg-white border rounded-2 px-2 py-1 text-dark small text-decoration-none d-inline-flex align-items-center gap-1 shadow-none" style="font-size: 0.75rem;">
                                        <i class="bi bi-paperclip text-primary"></i>
                                        <span>{{ $attachment->original_name }}</span>
                                        <span class="text-muted">({{ $sizeKb }} KB)</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Reply Submission Section -->
        @php
            $portalType = $portal ?? 'customer';
            $replyRoute = match($portalType) {
                'agent' => route('agent.tickets.reply', $ticket),
                'admin' => route('admin.tickets.reply', $ticket),
                default => route('customer.tickets.reply', $ticket),
            };
            $isClosed = $ticket->status->value === 'closed';
        @endphp

        @if($isClosed)
            <div class="p-3 bg-light border rounded-3 text-center text-muted small">
                <i class="bi bi-lock me-1"></i> This ticket is closed. Discussion and replies are locked.
            </div>
        @else
            <div class="border-top pt-4">
                <h6 class="fw-bold text-dark display-font fs-6 mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-reply-fill text-primary"></i>
                    <span>Leave a Reply</span>
                </h6>

                <form method="POST" action="{{ $replyRoute }}" enctype="multipart/form-data" id="ticketReplyForm">
                    @csrf

                    @if($portalType !== 'customer')
                        <!-- Staff Internal Note Toggle -->
                        <div class="mb-3 p-2 px-3 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_internal" value="1" id="isInternalCheck" onchange="toggleInternalNoteMode(this)">
                                <label class="form-check-label fw-semibold small text-dark" for="isInternalCheck">
                                    <i class="bi bi-lock-fill text-warning me-1"></i> Post as Internal Staff Note
                                </label>
                            </div>
                            <span class="text-muted small" style="font-size: 0.75rem;">Only visible to agents and admins</span>
                        </div>
                    @endif

                    <div class="mb-3">
                        <textarea name="body" id="replyBody" rows="4" class="form-control" placeholder="Write your response here..." required></textarea>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                        <div>
                            <label for="attachments" class="form-label small text-muted mb-1 d-block" style="font-size: 0.75rem;">
                                <i class="bi bi-paperclip me-1"></i>Attach Files (Max 5 files, 5MB each)
                            </label>
                            <input type="file" name="attachments[]" id="attachments" class="form-control form-control-sm" multiple>
                        </div>
                        <div class="mt-auto">
                            <button type="submit" id="submitReplyBtn" class="btn btn-portal-primary px-4 py-2 d-inline-flex align-items-center gap-2">
                                <i class="bi bi-send-fill" id="replyBtnIcon"></i>
                                <span id="replyBtnText">Send Public Reply</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <script>
                function toggleInternalNoteMode(checkbox) {
                    const btn = document.getElementById('submitReplyBtn');
                    const text = document.getElementById('replyBtnText');
                    const icon = document.getElementById('replyBtnIcon');
                    const textarea = document.getElementById('replyBody');

                    if (checkbox.checked) {
                        btn.classList.remove('btn-portal-primary');
                        btn.classList.add('btn-warning');
                        btn.style.color = '#78350f';
                        text.textContent = 'Post Internal Note';
                        icon.className = 'bi bi-lock-fill';
                        textarea.style.borderColor = '#f59e0b';
                        textarea.style.backgroundColor = '#fffbeb';
                    } else {
                        btn.classList.add('btn-portal-primary');
                        btn.classList.remove('btn-warning');
                        btn.style.color = '#ffffff';
                        text.textContent = 'Send Public Reply';
                        icon.className = 'bi bi-send-fill';
                        textarea.style.borderColor = '';
                        textarea.style.backgroundColor = '';
                    }
                }
            </script>
        @endif
    </div>
</div>
