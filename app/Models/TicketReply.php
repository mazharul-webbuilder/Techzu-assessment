<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketReply extends Model
{
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
}
