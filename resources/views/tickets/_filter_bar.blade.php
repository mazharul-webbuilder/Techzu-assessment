<div class="p-3 px-4 border-bottom bg-light">
    <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-center">
        {{-- Preserve active tab if present --}}
        @if(request('tab'))
            <input type="hidden" name="tab" value="{{ request('tab') }}">
        @endif

        {{-- Keyword / Ticket Number Search --}}
        <div class="col-12 col-md-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text"
                       name="search"
                       class="form-control border-start-0"
                       placeholder="{{ $portal === 'customer' ? 'Search subject...' : 'Search subject or #TKT...' }}"
                       value="{{ request('search') }}">
            </div>
        </div>

        {{-- Status Filter --}}
        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>All Active</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="waiting_for_customer" {{ request('status') === 'waiting_for_customer' ? 'selected' : '' }}>Waiting for Customer</option>
                <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
        </div>

        {{-- Priority Filter --}}
        <div class="col-6 col-md-2">
            <select name="priority" class="form-select form-select-sm">
                <option value="">All Priorities</option>
                <option value="critical" {{ request('priority') === 'critical' ? 'selected' : '' }}>Critical</option>
                <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
            </select>
        </div>

        {{-- Category Filter --}}
        @if(!empty($categories))
            <div class="col-6 col-md-2">
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- SLA Status Filter (Staff only) --}}
        @if($portal !== 'customer')
            <div class="col-6 col-md-2">
                <select name="sla_status" class="form-select form-select-sm">
                    <option value="">Any SLA State</option>
                    <option value="within" {{ request('sla_status') === 'within' ? 'selected' : '' }}>Healthy (Within SLA)</option>
                    <option value="approaching" {{ request('sla_status') === 'approaching' ? 'selected' : '' }}>Warning (Approaching)</option>
                    <option value="breached" {{ request('sla_status') === 'breached' ? 'selected' : '' }}>Breached</option>
                </select>
            </div>
        @endif

        {{-- Agent Filter (Admin only) --}}
        @if($portal === 'admin' && !empty($agents))
            <div class="col-6 col-md-2">
                <select name="agent_id" class="form-select form-select-sm">
                    <option value="">All Agents</option>
                    <option value="unassigned" {{ request('agent_id') === 'unassigned' ? 'selected' : '' }}>Unassigned Only</option>
                    @foreach($agents as $agentItem)
                        <option value="{{ $agentItem->id }}" {{ (string) request('agent_id') === (string) $agentItem->id ? 'selected' : '' }}>
                            {{ $agentItem->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Action Buttons --}}
        <div class="col-auto d-flex align-items-center gap-2 ms-auto">
            <button type="submit" class="btn btn-sm btn-primary px-3 rounded-2 shadow-none">
                <i class="bi bi-funnel-fill me-1"></i> Filter
            </button>
            @if(request()->hasAny(['search', 'status', 'priority', 'category_id', 'sla_status', 'agent_id', 'date_from', 'date_to', 'ticket_number']))
                <a href="{{ url()->current() . (request('tab') ? '?tab=' . request('tab') : '') }}" class="btn btn-sm btn-outline-secondary px-2 rounded-2 shadow-none" title="Reset Filters">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
        </div>
    </form>
</div>
