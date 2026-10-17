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

class TicketCreatedNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject("[{$this->ticket->ticket_number}] Ticket Received: {$this->ticket->subject}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your support ticket has been received and logged successfully.")
            ->line("**Ticket Number:** #{$this->ticket->ticket_number}")
            ->line("**Subject:** {$this->ticket->subject}")
            ->line("**Priority:** {$this->ticket->priority->label()}")
            ->action('View Ticket Details', $url)
            ->line('Our support team will review your request and get back to you shortly.');
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
            'type' => 'ticket_created',
            'title' => 'Ticket Received',
            'message' => "Ticket #{$this->ticket->ticket_number} logged successfully.",
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
