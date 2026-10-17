<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'reply_id',
        'uploaded_by_type',
        'uploaded_by_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * The ticket this file is attached to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The specific reply this file belongs to (null if attached to main ticket).
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(TicketReply::class, 'reply_id');
    }

    /**
     * Polymorphic user who uploaded this file (Customer, Agent, or Admin).
     */
    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
