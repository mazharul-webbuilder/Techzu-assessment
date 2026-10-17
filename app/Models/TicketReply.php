<?php

namespace App\Models;

use App\Traits\HasBusinessTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketReply extends Model
{
    use HasBusinessTimezone;
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'author_type',
        'author_id',
        'body',
        'is_internal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * The parent ticket for this reply.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Polymorphic author (Customer, Agent, or Admin).
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Attachments specifically linked to this reply.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'reply_id');
    }

    /**
     * Scope query to only include replies visible to the specified actor.
     *
     * Customers NEVER see internal staff notes at the database query level.
     * Staff agents and administrators can view all public replies and internal notes.
     */
    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Contracts\Auth\Authenticatable|\Illuminate\Database\Eloquent\Model $actor): \Illuminate\Database\Eloquent\Builder
    {
        if ($actor instanceof Customer) {
            return $query->where('is_internal', false);
        }

        return $query;
    }
}
