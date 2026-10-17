<?php

namespace App\Services\Common;

use App\Models\TicketSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates collision-proof sequential ticket identifiers in the format:
 * TKT-YYYY-NNNNNN (e.g. TKT-2026-000001).
 *
 * Concurrency Safety & Guarantees:
 * - Uses atomic SELECT ... FOR UPDATE row locking on ticket_sequences per year.
 * - Idempotently initializes the sequence row via INSERT IGNORE if missing.
 * - Restarts numbering from 1 on each new calendar year.
 * - Must be invoked within a DB transaction to hold the lock until commit.
 */
class TicketNumberGenerator
{
    /**
     * Generate the next formatted ticket number for the specified date (defaults to current time).
     *
     * @param Carbon|null $now Optional Carbon instance for deterministic testing
     * @return string Formatted ticket number: TKT-YYYY-NNNNNN
     */
    public function next(?Carbon $now = null): string
    {
        $year = (int) ($now ?? Carbon::now())->format('Y');

        return DB::transaction(function () use ($year) {
            // 1. Ensure a row exists for the year without blowing up on duplicates
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('INSERT OR IGNORE INTO ticket_sequences (year, last_number, created_at, updated_at) VALUES (?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$year]);
            } else {
                DB::statement('INSERT IGNORE INTO ticket_sequences (year, last_number, created_at, updated_at) VALUES (?, 0, NOW(), NOW())', [$year]);
            }

            // 2. Lock the row for update to prevent concurrent race conditions
            $sequence = TicketSequence::where('year', $year)->lockForUpdate()->firstOrFail();

            // 3. Increment the sequence number
            $sequence->last_number++;
            $sequence->save();

            // 4. Return standard zero-padded 6-digit identifier
            return sprintf('TKT-%04d-%06d', $year, $sequence->last_number);
        });
    }
}
