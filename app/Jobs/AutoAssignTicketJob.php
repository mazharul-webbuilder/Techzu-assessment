<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\Common\TicketAssignmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background job to automatically assign newly created tickets
 * to available agents with the lowest workload.
 */
class AutoAssignTicketJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ticket $ticket
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TicketAssignmentService $assignmentService): void
    {
        // Only run auto-assignment if the ticket is still unassigned
        if ($this->ticket->assigned_agent_id === null) {
            $assignmentService->autoAssign($this->ticket);
        }
    }
}
