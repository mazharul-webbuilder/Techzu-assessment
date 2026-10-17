<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_number',
        'external_id',
        'subject',
        'description',
        'priority',
        'status',
        'category_id',
        'customer_id',
        'assigned_agent_id',
        'sla_due_at',
        'sla_warning_at',
        'sla_warning_notified_at',
        'sla_breach_notified_at',
        'escalated_at',
        'resolved_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => TicketStatus::class,
            'sla_due_at' => 'datetime',
            'sla_warning_at' => 'datetime',
            'sla_warning_notified_at' => 'datetime',
            'sla_breach_notified_at' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Customer who created the ticket.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Support agent assigned to resolve the ticket.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    /**
     * Assigned category for the ticket.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Chronological replies and notes for this ticket.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * Files attached to this ticket.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Immutable audit trail activities for this ticket.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    /**
     * Scope query to only include tickets visible to the specified actor.
     *
     * - Customer: Own tickets only.
     * - Agent: Assigned tickets or unassigned tickets in the pool.
     * - Admin: All tickets across the system.
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Contracts\Auth\Authenticatable|\Illuminate\Database\Eloquent\Model $actor): \Illuminate\Database\Eloquent\Builder
    {
        if ($actor instanceof Customer) {
            return $query->where('customer_id', $actor->id);
        }

        if ($actor instanceof Agent) {
            return $query->where(function ($q) use ($actor) {
                $q->where('assigned_agent_id', $actor->id)
                  ->orWhereNull('assigned_agent_id');
            });
        }

        if ($actor instanceof Admin) {
            return $query;
        }

        return $query->whereRaw('0 = 1'); // Unauthorized/unknown actor gets empty results
    }

    /**
     * Scope query to only include active tickets that require ongoing handling.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', TicketStatus::activeValues());
    }

    /**
     * Scope query to only include tickets that have no assigned agent.
     */
    public function scopeUnassigned(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('assigned_agent_id');
    }
}
