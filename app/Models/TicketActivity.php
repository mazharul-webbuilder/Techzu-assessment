<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketActivity extends Model
{
    /**
     * Disable updated_at since this is an append-only audit trail.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'actor_type',
        'actor_id',
        'event',
        'old_values',
        'new_values',
        'description',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The ticket associated with this activity.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Polymorphic actor who initiated this activity (null for automated system events).
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
