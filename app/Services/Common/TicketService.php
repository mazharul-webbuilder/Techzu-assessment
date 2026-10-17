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
        return DB::transaction(function () use ($customer, $data) {
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
    }

    /**
     * Get paginated tickets submitted by the customer with necessary relationships.
     */
    public function getTicketsForCustomer(Customer $customer, int $perPage = 15): LengthAwarePaginator
    {
        return Ticket::where('customer_id', $customer->id)
            ->with(['category:id,name', 'assignedAgent:id,name'])
            ->latest()
            ->paginate($perPage);
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
     * Load ticket details, attachments, and chronological activity history.
     */
    public function getTicketDetails(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'category:id,name',
            'assignedAgent:id,name',
            'attachments',
            'activities' => fn ($q) => $q->latest('created_at'),
            'activities.actor',
        ]);
    }
}
