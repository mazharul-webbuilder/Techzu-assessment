<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class SlaApproachingNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 180];

    public function __construct(
        public Ticket $ticket
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->resolveTicketUrl($notifiable);
        $due = $this->ticket->sla_due_at?->toDayDateTimeString() ?? 'N/A';

        return (new MailMessage)
            ->subject("[WARNING] SLA Approaching: #{$this->ticket->ticket_number} - {$this->ticket->subject}")
            ->greeting("Attention {$notifiable->name},")
            ->line("Ticket #{$this->ticket->ticket_number} has consumed 80% of its resolution SLA duration.")
            ->line("**Subject:** {$this->ticket->subject}")
            ->line("**Priority:** {$this->ticket->priority->label()}")
            ->line("**Resolution Due By:** {$due}")
            ->action('Review Ticket Immediately', $url)
            ->line('Please take action promptly to avoid an SLA breach.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'type' => 'sla_approaching',
            'title' => 'SLA Approaching Breach',
            'message' => "Ticket #{$this->ticket->ticket_number} has reached 80% SLA elapsed time.",
            'url' => $this->resolveTicketUrl($notifiable),
        ];
    }

    protected function resolveTicketUrl(object $notifiable): string
    {
        if ($notifiable instanceof Customer) {
            return route('customer.tickets.show', $this->ticket);
        }

        if ($notifiable instanceof Agent) {
            return route('agent.tickets.show', $this->ticket);
        }

        return route('admin.tickets.show', $this->ticket);
    }
}
