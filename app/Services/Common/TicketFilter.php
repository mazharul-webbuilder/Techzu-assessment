<?php

namespace App\Services\Common;

use App\Enums\Priority;
use App\Enums\SlaStatus;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter service applying validated query string criteria to Ticket queries.
 *
 * Designed to strictly compose on top of Ticket::visibleTo($actor) so filters
 * can never widen the scope of what an actor is permitted to see.
 */
class TicketFilter
{
    /**
     * Apply filter criteria to the ticket query builder.
     *
     * @param Builder $query Base ticket query builder (should already include visibleTo($actor))
     * @param array<string, mixed> $filters Validated filter inputs
     * @return Builder
     */
    public function apply(Builder $query, array $filters): Builder
    {
        // 1. Ticket Number (Prefix search, e.g. TKT-2026-%)
        if (!empty($filters['ticket_number'])) {
            $prefix = trim($filters['ticket_number']);
            $query->where('ticket_number', 'like', "{$prefix}%");
        }

        // 2. Keyword Search on Subject & Description
        $search = $filters['search'] ?? $filters['keyword'] ?? null;
        if (!empty($search)) {
            $keyword = trim($search);
            $query->where(function (Builder $sub) use ($keyword) {
                $sub->where('subject', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // 3. Status Filter (Single status or active/closed grouping)
        if (!empty($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'active') {
                $query->whereIn('status', TicketStatus::activeValues());
            } elseif (in_array($status, ['resolved_closed', 'closed_all'], true)) {
                $query->whereIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value]);
            } elseif (TicketStatus::tryFrom($status)) {
                $query->where('status', $status);
            }
        }

        // 4. Priority Filter
        if (!empty($filters['priority'])) {
            $priority = $filters['priority'];
            if ($priority instanceof Priority) {
                $query->where('priority', $priority);
            } elseif (Priority::tryFrom($priority)) {
                $query->where('priority', $priority);
            }
        }

        // 5. Category Filter
        $categoryId = $filters['category_id'] ?? $filters['category'] ?? null;
        if (!empty($categoryId) && is_numeric($categoryId)) {
            $query->where('category_id', (int) $categoryId);
        }

        // 6. Assigned Agent Filter (including 'unassigned')
        $agent = $filters['assigned_agent'] ?? $filters['agent_id'] ?? null;
        if ($agent !== null && $agent !== '') {
            if ($agent === 'unassigned') {
                $query->whereNull('assigned_agent_id');
            } elseif (is_numeric($agent)) {
                $query->where('assigned_agent_id', (int) $agent);
            }
        }

        // 7. Customer Filter
        $customerId = $filters['customer_id'] ?? $filters['customer'] ?? null;
        if (!empty($customerId) && is_numeric($customerId)) {
            $query->where('customer_id', (int) $customerId);
        }

        // 8. Created Date Range
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // 9. SLA Status Filter (using Ticket scopes)
        if (!empty($filters['sla_status'])) {
            $sla = strtolower((string) $filters['sla_status']);
            if ($sla === SlaStatus::Breached->value) {
                $query->slaBreached();
            } elseif ($sla === SlaStatus::Approaching->value) {
                $query->slaApproaching();
            } elseif ($sla === SlaStatus::Within->value) {
                $query->slaWithin();
            }
        }

        // 10. Escalation Filter
        if (!empty($filters['escalated'])) {
            $query->whereNotNull('escalated_at');
        }

        return $query;
    }
}
