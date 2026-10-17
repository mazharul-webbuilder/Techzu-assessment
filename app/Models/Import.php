<?php

namespace App\Models;

use App\Traits\HasBusinessTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use HasBusinessTimezone;
    /**
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'skipped_duplicates',
        'error_report_path',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
            'skipped_duplicates' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Administrator who uploaded and triggered this batch import.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
