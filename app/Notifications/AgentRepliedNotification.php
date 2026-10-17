<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class AgentRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 180];

    public function __construct(
        public Ticket $ticket,
        public TicketReply $reply
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
        $preview = Str::limit(strip_tags($this->reply->body), 200);

        return (new MailMessage)
            ->subject("[{$this->ticket->ticket_number}] New Reply: {$this->ticket->subject}")
            ->greeting("Hello {$notifiable->name},")
            ->line("An agent has replied to your support ticket.")
            ->line("**Ticket Number:** #{$this->ticket->ticket_number}")
            ->line("**Subject:** {$this->ticket->subject}")
            ->line("**Reply Preview:** \"{$preview}\"")
            ->action('View Full Conversation', $url)
            ->line('You can view and reply directly through your customer portal.');
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
            'reply_id' => $this->reply->id,
            'type' => 'agent_replied',
            'title' => 'New Reply on Ticket',
            'message' => "An agent replied to ticket #{$this->ticket->ticket_number}.",
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
