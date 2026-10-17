<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSequence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'year',
        'last_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
