<?php

namespace App\Services\Common;

use App\Enums\ActivityEvent;
use App\Models\Ticket;
use App\Notifications\SlaApproachingNotification;
use App\Notifications\SlaBreachedNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;

/**
 * Service executing scheduled scans for SLA breach escalation and warning threshold alerts.
 *
 * Concurrency & Idempotency Design:
 * - Evaluates only ACTIVE tickets (status in [open, in_progress, waiting_for_customer]).
 * - Uses atomic conditional updates (e.g. WHERE key = ? AND sla_breach_notified_at IS NULL).
 * - Only the process that affects exactly 1 row wins the right to record audit log and send notifications.
 * - If a ticket skips past its warning (e.g. system downtime), breach handler also sets the warning flag
 *   to permanently prevent sending stale warning alerts later.
 */
class SlaEscalationService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {}

    /**
     * Scan active tickets, execute atomic breach escalations and approaching-breach warnings.
     *
     * @param CarbonInterface|null $now Reference timestamp (defaults to current time)
     * @return array{breaches: int, warnings: int} Count of newly processed actions
     */
    public function checkAndEscalate(?CarbonInterface $now = null): array
    {
        $currentTime = $now ?? now();
        $breachCount = 0;
        $warningCount = 0;

        // Breach Scan: Tickets past resolution deadline with no breach notification sent yet
        Ticket::query()
            ->active()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', $currentTime)
            ->whereNull('sla_breach_notified_at')
            ->chunkById(200, function ($tickets) use ($currentTime, &$breachCount) {
                foreach ($tickets as $ticket) {
                    // Atomic claim: only 1 winner updates the row
                    $affected = Ticket::query()
                        ->whereKey($ticket->id)
                        ->whereNull('sla_breach_notified_at')
                        ->update([
                            'sla_breach_notified_at' => $currentTime,
                            'escalated_at' => $ticket->escalated_at ?? $currentTime,
                            'sla_warning_notified_at' => $ticket->sla_warning_notified_at ?? $currentTime,
                        ]);

                    if ($affected === 1) {
                        $ticket->refresh();

                        $this->activityLogger->log(
                            ticket: $ticket,
                            event: ActivityEvent::Escalated,
                            actor: null,
                            old: ['escalated_at' => null],
                            new: ['escalated_at' => $ticket->escalated_at?->toIso8601String()],
                            description: "SLA deadline breached. Ticket automatically escalated to management."
                        );

                        $recipients = NotificationRecipients::forSlaBreached($ticket);
                        Notification::send($recipients, new SlaBreachedNotification($ticket));
                        $breachCount++;
                    }
                }
            });

        // Warning Scan: Tickets past warning threshold, before due date, with no warning sent yet
        Ticket::query()
            ->active()
            ->whereNotNull('sla_warning_at')
            ->where('sla_warning_at', '<=', $currentTime)
            ->where('sla_due_at', '>', $currentTime)
            ->whereNull('sla_warning_notified_at')
            ->chunkById(200, function ($tickets) use ($currentTime, &$warningCount) {
                foreach ($tickets as $ticket) {
                    // Atomic claim: only 1 winner updates the row
                    $affected = Ticket::query()
                        ->whereKey($ticket->id)
                        ->whereNull('sla_warning_notified_at')
                        ->update([
                            'sla_warning_notified_at' => $currentTime,
                        ]);

                    if ($affected === 1) {
                        $ticket->refresh();

                        $this->activityLogger->log(
                            ticket: $ticket,
                            event: ActivityEvent::SlaWarning,
                            actor: null,
                            old: [],
                            new: ['sla_warning_notified_at' => $currentTime->toIso8601String()],
                            description: "Ticket reached 80% SLA elapsed time warning threshold."
                        );

                        $recipients = NotificationRecipients::forSlaApproaching($ticket);
                        Notification::send($recipients, new SlaApproachingNotification($ticket));
                        $warningCount++;
                    }
                }
            });

        return [
            'breaches' => $breachCount,
            'warnings' => $warningCount,
        ];
    }
}
