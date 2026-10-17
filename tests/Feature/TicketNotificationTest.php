<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Notifications\AgentRepliedNotification;
use App\Notifications\CustomerRepliedNotification;
use App\Notifications\SlaApproachingNotification;
use App\Notifications\SlaBreachedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Services\Common\NotificationRecipients;
use App\Services\Common\TicketAssignmentService;
use App\Services\Common\TicketService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected TicketService $ticketService;
    protected TicketAssignmentService $assignmentService;

    protected Category $category;
    protected Customer $customer;
    protected Agent $agent;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ticketService = app(TicketService::class);
        $this->assignmentService = app(TicketAssignmentService::class);

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'tech-support',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Alice Customer',
            'email' => 'alice@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent = Agent::create([
            'name' => 'Bob Agent',
            'email' => 'bob@support.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->admin = Admin::create([
            'name' => 'Carol Admin',
            'email' => 'carol@admin.test',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_ticket_created_notifies_customer(): void
    {
        Notification::fake();

        // Disable auto-assign on create for clean isolation of ticket created event
        config(['helpdesk.auto_assign_on_create' => false]);

        $ticket = $this->ticketService->create($this->customer, [
            'subject' => 'Cannot login to account',
            'description' => 'Getting 500 error when submitting form',
            'category_id' => $this->category->id,
            'priority' => Priority::High,
        ]);

        Notification::assertSentTo(
            $this->customer,
            TicketCreatedNotification::class,
            function (TicketCreatedNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id;
            }
        );
    }

    public function test_manual_assignment_notifies_agent(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Issue needing assign',
            'description' => 'Needs attention',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        $this->assignmentService->assign($ticket, $this->agent, $this->admin);

        Notification::assertSentTo(
            $this->agent,
            TicketAssignedNotification::class,
            function (TicketAssignedNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id;
            }
        );
    }

    public function test_claim_ticket_notifies_agent(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-002',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Unassigned task',
            'description' => 'Needs claim',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        $this->assignmentService->claim($ticket, $this->agent);

        Notification::assertSentTo(
            $this->agent,
            TicketAssignedNotification::class,
            function (TicketAssignedNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id;
            }
        );
    }

    public function test_auto_assign_notifies_agent(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-003',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Auto-assignment task',
            'description' => 'Needs distribution',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        $success = $this->assignmentService->autoAssign($ticket);
        $this->assertTrue($success);

        Notification::assertSentTo(
            $this->agent,
            TicketAssignedNotification::class,
            function (TicketAssignedNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id;
            }
        );
    }

    public function test_agent_public_reply_notifies_customer(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-004',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Ticket awaiting agent reply',
            'description' => 'Inquiry',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
        ]);

        $reply = $this->ticketService->reply(
            ticket: $ticket,
            author: $this->agent,
            body: 'We have resolved the network glitch. Please test now.',
            internal: false,
        );

        Notification::assertSentTo(
            $this->customer,
            AgentRepliedNotification::class,
            function (AgentRepliedNotification $notification) use ($ticket, $reply) {
                return $notification->ticket->id === $ticket->id
                    && $notification->reply->id === $reply->id;
            }
        );
    }

    public function test_internal_note_never_notifies_customer(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-005',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Confidential internal note test',
            'description' => 'Inquiry',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
        ]);

        $this->ticketService->reply(
            ticket: $ticket,
            author: $this->agent,
            body: 'Customer account flagged for manual payment verification.',
            internal: true,
        );

        // Crucial requirement: Customer must NEVER receive notification for internal notes
        Notification::assertNotSentTo($this->customer, AgentRepliedNotification::class);
        Notification::assertNothingSent();
    }

    public function test_customer_reply_notifies_assigned_agent(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-006',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Assigned ticket reply test',
            'description' => 'Inquiry',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
        ]);

        $reply = $this->ticketService->reply(
            ticket: $ticket,
            author: $this->customer,
            body: 'Thank you! The issue appears to be partially resolved.',
            internal: false,
        );

        Notification::assertSentTo(
            $this->agent,
            CustomerRepliedNotification::class,
            function (CustomerRepliedNotification $notification) use ($ticket, $reply) {
                return $notification->ticket->id === $ticket->id
                    && $notification->reply->id === $reply->id;
            }
        );

        Notification::assertNotSentTo($this->admin, CustomerRepliedNotification::class);
    }

    public function test_customer_reply_notifies_admins_when_unassigned(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-007',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => null,
            'subject' => 'Unassigned ticket customer response',
            'description' => 'Inquiry',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        $reply = $this->ticketService->reply(
            ticket: $ticket,
            author: $this->customer,
            body: 'Additional log information attached.',
            internal: false,
        );

        Notification::assertSentTo(
            $this->admin,
            CustomerRepliedNotification::class,
            function (CustomerRepliedNotification $notification) use ($ticket, $reply) {
                return $notification->ticket->id === $ticket->id
                    && $notification->reply->id === $reply->id;
            }
        );
    }

    public function test_ticket_resolved_notifies_customer(): void
    {
        Notification::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-008',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Resolving ticket test',
            'description' => 'Inquiry',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
        ]);

        $this->ticketService->changeStatus($ticket, TicketStatus::Resolved, $this->agent);

        Notification::assertSentTo(
            $this->customer,
            TicketResolvedNotification::class,
            function (TicketResolvedNotification $notification) use ($ticket) {
                return $notification->ticket->id === $ticket->id;
            }
        );
    }

    public function test_all_seven_notification_classes_implement_should_queue_and_after_commit(): void
    {
        $classes = [
            TicketCreatedNotification::class,
            TicketAssignedNotification::class,
            AgentRepliedNotification::class,
            CustomerRepliedNotification::class,
            TicketResolvedNotification::class,
            SlaApproachingNotification::class,
            SlaBreachedNotification::class,
        ];

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-009',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Queue structure check',
            'description' => 'Testing class parameters',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        $reply = $ticket->replies()->create([
            'author_type' => $this->agent->getMorphClass(),
            'author_id' => $this->agent->id,
            'body' => 'Test body',
            'is_internal' => false,
        ]);

        foreach ($classes as $class) {
            $instance = match ($class) {
                AgentRepliedNotification::class, CustomerRepliedNotification::class => new $class($ticket, $reply),
                default => new $class($ticket),
            };

            $this->assertInstanceOf(ShouldQueue::class, $instance, "{$class} must implement ShouldQueue");
            $this->assertTrue($instance->afterCommit, "{$class} must have afterCommit = true");
            $this->assertSame(3, $instance->tries, "{$class} must have 3 tries");
            $this->assertEquals([10, 60, 180], $instance->backoff, "{$class} must have backoff configured");

            $channels = $instance->via($this->customer);
            $this->assertContains('mail', $channels, "{$class} must support mail channel");
            $this->assertContains('database', $channels, "{$class} must support database channel");

            // Verify database serialization structure
            $data = $instance->toArray($this->customer);
            $this->assertArrayHasKey('ticket_id', $data);
            $this->assertArrayHasKey('ticket_number', $data);
            $this->assertArrayHasKey('url', $data);
        }
    }

    public function test_notification_recipients_for_sla_warnings_and_breaches(): void
    {
        // 1. Ticket with assigned agent
        $assignedTicket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-010',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Assigned SLA check',
            'description' => 'Testing recipients',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
        ]);

        // SLA Approaching recipient when assigned -> agent
        $approachingRecipients = NotificationRecipients::forSlaApproaching($assignedTicket);
        $this->assertInstanceOf(Agent::class, $approachingRecipients);
        $this->assertSame($this->agent->id, $approachingRecipients->id);

        // SLA Breached recipients when assigned -> agent + admins
        $breachedRecipients = NotificationRecipients::forSlaBreached($assignedTicket);
        $this->assertCount(2, $breachedRecipients); // 1 agent + 1 admin
        $breachedEmails = $breachedRecipients->pluck('email')->all();
        $this->assertContains($this->agent->email, $breachedEmails);
        $this->assertContains($this->admin->email, $breachedEmails);

        // 2. Unassigned ticket
        $unassignedTicket = Ticket::create([
            'ticket_number' => 'TCK-NOTIF-011',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => null,
            'subject' => 'Unassigned SLA check',
            'description' => 'Testing recipients',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        // SLA Approaching recipient when unassigned -> admins
        $unassignedApproaching = NotificationRecipients::forSlaApproaching($unassignedTicket);
        $this->assertCount(1, $unassignedApproaching);
        $this->assertSame($this->admin->id, $unassignedApproaching->first()->id);

        // SLA Breached recipients when unassigned -> admins
        $unassignedBreached = NotificationRecipients::forSlaBreached($unassignedTicket);
        $this->assertCount(1, $unassignedBreached);
        $this->assertSame($this->admin->id, $unassignedBreached->first()->id);
    }
}
