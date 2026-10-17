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
    public function handle(TicketAssignmentService $assignmentService, \App\Services\Common\BusinessHoursCalculator $calculator): void
    {
        // If current time is outside business working hours, release job back to queue
        // so ticket is not assigned to any agent until the next working window opens
        if (!$calculator->isWithinBusinessHours()) {
            $nextOpening = $calculator->nextBusinessOpeningTime();
            $delaySeconds = max(1, $nextOpening->getTimestamp() - now()->getTimestamp());
            $this->release($delaySeconds);
            return;
        }

        // Only run auto-assignment if the ticket is still unassigned
        if ($this->ticket->assigned_agent_id === null) {
            $assignmentService->autoAssign($this->ticket);
        }
    }
}
