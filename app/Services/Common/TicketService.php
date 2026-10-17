<?php

namespace App\Services\Common;

use App\Enums\ActivityEvent;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service managing core ticket lifecycle operations and database queries.
 *
 * All state mutations are wrapped in database transactions to guarantee atomicity.
 */
class TicketService
{
    public function __construct(
        protected TicketNumberGenerator $numberGenerator,
        protected SlaService $slaService,
        protected ActivityLogger $activityLogger
    ) {}

    /**
     * Create a new support ticket submitted by a customer.
     *
     * Wrapped in a single DB::transaction so that sequence numbering,
     * record creation, and activity audit logging commit or rollback together.
     *
     * @param Customer $customer The authenticated customer creating the ticket
     * @param array<string, mixed> $data Validated input data
     */
    public function create(Customer $customer, array $data): Ticket
    {
        $ticket = DB::transaction(function () use ($customer, $data) {
            $priority = $data['priority'] instanceof Priority
                ? $data['priority']
                : Priority::from($data['priority']);

            // 1. Calculate SLA resolution and warning deadlines
            $deadlines = $this->slaService->calculateDeadlines($priority);

            // 2. Obtain atomic sequential ticket number (e.g. TKT-2026-000001)
            $ticketNumber = $this->numberGenerator->next();

            // 3. Persist the ticket with initial 'open' status
            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'customer_id' => $customer->id,
                'category_id' => $data['category_id'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => TicketStatus::Open,
                'sla_due_at' => $deadlines['sla_due_at'],
                'sla_warning_at' => $deadlines['sla_warning_at'],
            ]);

            // 4. Record the immutable 'created' activity audit entry
            $this->activityLogger->log(
                ticket: $ticket,
                event: ActivityEvent::Created,
                actor: $customer,
                old: [],
                new: [
                    'status' => TicketStatus::Open->value,
                    'priority' => $priority->value,
                ],
                description: "Ticket created by {$customer->name}"
            );

            // 5. Store uploaded attachments if provided
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $disk = config('helpdesk.attachments.disk', 'local');
                foreach ($data['attachments'] as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $extension = $file->getClientOriginalExtension() ?: $file->extension();
                        $filename = \Illuminate\Support\Str::uuid()->toString() . '.' . $extension;
                        $path = $file->storeAs("tickets/{$ticket->id}", $filename, $disk);

                        $ticket->attachments()->create([
                            'uploaded_by_type' => $customer->getMorphClass(),
                            'uploaded_by_id' => $customer->getKey(),
                            'disk' => $disk,
                            'path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                            'size_bytes' => $file->getSize(),
                        ]);
                    }
                }
            }

            return $ticket;
        });

        // 6. Trigger automatic workload-based assignment if enabled
        if (config('helpdesk.auto_assign_on_create', true)) {
            \App\Jobs\AutoAssignTicketJob::dispatch($ticket);
        }

        return $ticket;
    }

    /**
     * Get paginated tickets visible to the specified actor.
     */
    public function getTicketsForActor(\Illuminate\Contracts\Auth\Authenticatable|\Illuminate\Database\Eloquent\Model $actor, int $perPage = 15): LengthAwarePaginator
    {
        return Ticket::query()
            ->visibleTo($actor)
            ->with(['customer:id,name', 'category:id,name', 'assignedAgent:id,name'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get paginated tickets submitted by the customer with necessary relationships.
     */
    public function getTicketsForCustomer(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return $this->getTicketsForActor($customer, $perPage);
    }

    /**
     * Get all active categories for ticket submission.
     *
     * @return Collection<int, Category>
     */
    public function getActiveCategories(): Collection
    {
        return Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Load ticket details, attachments, visible replies, and chronological activity history.
     */
    public function getTicketDetails(Ticket $ticket, ?\Illuminate\Contracts\Auth\Authenticatable $actor = null): Ticket
    {
        return $ticket->load([
            'customer:id,name',
            'category:id,name',
            'assignedAgent:id,name',
            'attachments',
            'replies' => fn ($q) => $actor
                ? $q->visibleTo($actor)->with(['author', 'attachments'])->oldest()
                : $q->with(['author', 'attachments'])->oldest(),
            'activities' => fn ($q) => $q->latest('created_at'),
            'activities.actor',
        ]);
    }

    /**
     * Post a reply or staff internal note to a ticket.
     *
     * @param Ticket $ticket Target ticket
     * @param \Illuminate\Database\Eloquent\Model $author Customer, Agent, or Admin
     * @param string $body Text body of reply
     * @param bool $internal True for staff internal note (enforced false for customers)
     * @param array<int, \Illuminate\Http\UploadedFile> $attachments
     */
    public function reply(
        Ticket $ticket,
        \Illuminate\Database\Eloquent\Model $author,
        string $body,
        bool $internal = false,
        array $attachments = []
    ): \App\Models\TicketReply {
        return DB::transaction(function () use ($ticket, $author, $body, $internal, $attachments) {
            // Security constraint: Customers can NEVER author internal staff notes
            if ($author instanceof Customer) {
                $internal = false;
            }

            // 1. Create the reply
            $reply = \App\Models\TicketReply::create([
                'ticket_id' => $ticket->id,
                'author_type' => $author->getMorphClass(),
                'author_id' => $author->getKey(),
                'body' => $body,
                'is_internal' => $internal,
            ]);

            // 2. Handle reply attachments if provided
            if (!empty($attachments) && is_array($attachments)) {
                $disk = config('helpdesk.attachments.disk', 'local');
                foreach ($attachments as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $extension = $file->getClientOriginalExtension() ?: $file->extension();
                        $filename = \Illuminate\Support\Str::uuid()->toString() . '.' . $extension;
                        $path = $file->storeAs("tickets/{$ticket->id}", $filename, $disk);

                        $reply->attachments()->create([
                            'ticket_id' => $ticket->id,
                            'uploaded_by_type' => $author->getMorphClass(),
                            'uploaded_by_id' => $author->getKey(),
                            'disk' => $disk,
                            'path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                            'size_bytes' => $file->getSize(),
                        ]);
                    }
                }
            }

            // 3. Customer response lifecycle side-effects:
            // - Customer reply on waiting_for_customer moves the ticket to in_progress
            // - Customer reply on resolved reopens the ticket (in_progress if assigned, else open)
            if ($author instanceof Customer) {
                if ($ticket->status === TicketStatus::WaitingForCustomer) {
                    $oldStatus = $ticket->status;
                    $ticket->update(['status' => TicketStatus::InProgress]);
                    $this->activityLogger->log(
                        ticket: $ticket,
                        event: ActivityEvent::StatusChanged,
                        actor: $author,
                        old: ['status' => $oldStatus->value],
                        new: ['status' => TicketStatus::InProgress->value],
                        description: "Status automatically moved to In Progress upon customer response"
                    );
                } elseif ($ticket->status === TicketStatus::Resolved) {
                    $oldStatus = $ticket->status;
                    $newStatus = $ticket->assigned_agent_id ? TicketStatus::InProgress : TicketStatus::Open;
                    $ticket->update([
                        'status' => $newStatus,
                        'resolved_at' => null,
                    ]);
                    $this->activityLogger->log(
                        ticket: $ticket,
                        event: ActivityEvent::StatusChanged,
                        actor: $author,
                        old: ['status' => $oldStatus->value],
                        new: ['status' => $newStatus->value],
                        description: "Ticket reopened by customer response"
                    );
                }
            }

            // 4. Record audit activity
            $this->activityLogger->log(
                ticket: $ticket,
                event: $internal ? ActivityEvent::InternalNoteAdded : ActivityEvent::Replied,
                actor: $author,
                old: [],
                new: ['reply_id' => $reply->id, 'is_internal' => $internal],
                description: $internal
                    ? "Internal note added by {$author->name}"
                    : "Public reply posted by {$author->name}"
            );

            return $reply;
        });
    }

    /**
     * Transition ticket status following the state machine rules.
     *
     * @throws \App\Exceptions\InvalidStatusTransitionException
     */
    public function changeStatus(Ticket $ticket, TicketStatus $to, \Illuminate\Database\Eloquent\Model $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $actor) {
            $oldStatus = $ticket->status;

            if ($oldStatus === $to) {
                return $ticket;
            }

            if (!$oldStatus->canTransitionTo($to)) {
                throw new \App\Exceptions\InvalidStatusTransitionException(
                    "Cannot transition ticket status from '{$oldStatus->label()}' to '{$to->label()}'."
                );
            }

            $updates = ['status' => $to];

            if ($to === TicketStatus::Resolved) {
                $updates['resolved_at'] = now();
            } elseif ($to === TicketStatus::Closed) {
                $updates['closed_at'] = now();
            } elseif ($to === TicketStatus::InProgress && $oldStatus === TicketStatus::Resolved) {
                $updates['resolved_at'] = null; // Reopening
            }

            $ticket->update($updates);

            $this->activityLogger->log(
                ticket: $ticket,
                event: ActivityEvent::StatusChanged,
                actor: $actor,
                old: ['status' => $oldStatus->value],
                new: ['status' => $to->value],
                description: "Status changed from {$oldStatus->label()} to {$to->label()} by {$actor->name}"
            );

            return $ticket;
        });
    }

    /**
     * Change ticket priority, recalculating SLA targets and resetting warning flags.
     */
    public function changePriority(Ticket $ticket, Priority $to, \Illuminate\Database\Eloquent\Model $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $actor) {
            $oldPriority = $ticket->priority;

            if ($oldPriority === $to) {
                return $ticket;
            }

            // Recalculate SLA resolution and warning targets for the new priority
            $deadlines = $this->slaService->calculateDeadlines($to);

            $ticket->update([
                'priority' => $to,
                'sla_due_at' => $deadlines['sla_due_at'],
                'sla_warning_at' => $deadlines['sla_warning_at'],
                'sla_warning_notified_at' => null,
                'sla_breach_notified_at' => null,
            ]);

            $this->activityLogger->log(
                ticket: $ticket,
                event: ActivityEvent::PriorityChanged,
                actor: $actor,
                old: ['priority' => $oldPriority->value],
                new: ['priority' => $to->value],
                description: "Priority updated from {$oldPriority->label()} to {$to->label()} by {$actor->name}"
            );

            return $ticket;
        });
    }

    /**
     * Mark ticket as resolved.
     */
    public function resolve(Ticket $ticket, \Illuminate\Database\Eloquent\Model $actor): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Resolved, $actor);
    }

    /**
     * Mark ticket as closed.
     */
    public function close(Ticket $ticket, \Illuminate\Database\Eloquent\Model $actor): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Closed, $actor);
    }
}
