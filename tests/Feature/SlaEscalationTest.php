<?php

namespace Tests\Feature;

use App\Enums\ActivityEvent;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Notifications\SlaApproachingNotification;
use App\Notifications\SlaBreachedNotification;
use App\Services\Common\SlaEscalationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected SlaEscalationService $service;
    protected Category $category;
    protected Customer $customer;
    protected Agent $agent;
    protected Admin $admin1;
    protected Admin $admin2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SlaEscalationService::class);

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'tech-support',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Alice Customer',
            'email' => 'alice@customer.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent = Agent::create([
            'name' => 'Bob Agent',
            'email' => 'bob@agent.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->admin1 = Admin::create([
            'name' => 'Carol Admin',
            'email' => 'carol@admin.test',
            'password' => bcrypt('password'),
        ]);

        $this->admin2 = Admin::create([
            'name' => 'Dave Admin',
            'email' => 'dave@admin.test',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_ticket_past_warning_time_gets_one_warning_and_is_idempotent(): void
    {
        Notification::fake();

        $now = Carbon::parse('2026-10-18 12:00:00', 'UTC');
        Carbon::setTestNow($now);

        // Warning was at 11:30 (past), due is at 13:00 (future), no warning sent yet
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-WARN-001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Approaching warning test',
            'description' => 'Needs attention soon',
            'priority' => Priority::High,
            'status' => TicketStatus::InProgress,
            'sla_warning_at' => $now->copy()->subMinutes(30),
            'sla_due_at' => $now->copy()->addHour(),
            'sla_warning_notified_at' => null,
            'sla_breach_notified_at' => null,
        ]);

        // First run: should trigger warning
        $firstResults = $this->service->checkAndEscalate($now);
        $this->assertSame(0, $firstResults['breaches']);
        $this->assertSame(1, $firstResults['warnings']);

        $ticket->refresh();
        $this->assertNotNull($ticket->sla_warning_notified_at);
        $this->assertNull($ticket->escalated_at);

        Notification::assertSentTo(
            $this->agent,
            SlaApproachingNotification::class,
            fn ($n) => $n->ticket->id === $ticket->id
        );

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event' => ActivityEvent::SlaWarning->value,
            'actor_type' => null,
            'actor_id' => null,
        ]);

        // Second run: IDEMPOTENCY check - running again must send nothing and change nothing
        $secondResults = $this->service->checkAndEscalate($now);
        $this->assertSame(0, $secondResults['breaches']);
        $this->assertSame(0, $secondResults['warnings']);

        $activitiesCount = TicketActivity::where('ticket_id', $ticket->id)->count();
        $this->assertSame(1, $activitiesCount);
    }

    public function test_ticket_past_due_time_is_escalated_once_and_is_idempotent(): void
    {
        Notification::fake();

        $now = Carbon::parse('2026-10-18 14:00:00', 'UTC');
        Carbon::setTestNow($now);

        // Ticket past both warning and due deadline with no notifications sent
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-BREACH-001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Breached ticket escalation test',
            'description' => 'Deadline missed',
            'priority' => Priority::Critical,
            'status' => TicketStatus::InProgress,
            'sla_warning_at' => $now->copy()->subHours(2),
            'sla_due_at' => $now->copy()->subMinutes(15),
            'sla_warning_notified_at' => null,
            'sla_breach_notified_at' => null,
        ]);

        // First run: should escalate and notify agent + all admins
        $firstResults = $this->service->checkAndEscalate($now);
        $this->assertSame(1, $firstResults['breaches']);
        $this->assertSame(0, $firstResults['warnings']);

        $ticket->refresh();
        $this->assertNotNull($ticket->escalated_at);
        $this->assertNotNull($ticket->sla_breach_notified_at);
        // Notice: If warning was never sent, breach scan also sets warning flag to prevent stale alerts
        $this->assertNotNull($ticket->sla_warning_notified_at);

        Notification::assertSentTo(
            $this->agent,
            SlaBreachedNotification::class,
            fn ($n) => $n->ticket->id === $ticket->id
        );

        Notification::assertSentTo(
            $this->admin1,
            SlaBreachedNotification::class,
            fn ($n) => $n->ticket->id === $ticket->id
        );

        Notification::assertSentTo(
            $this->admin2,
            SlaBreachedNotification::class,
            fn ($n) => $n->ticket->id === $ticket->id
        );

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event' => ActivityEvent::Escalated->value,
            'actor_type' => null,
            'actor_id' => null,
        ]);

        // Second run: IDEMPOTENCY check - running again must not re-escalate
        $secondResults = $this->service->checkAndEscalate($now);
        $this->assertSame(0, $secondResults['breaches']);
        $this->assertSame(0, $secondResults['warnings']);

        $activitiesCount = TicketActivity::where('ticket_id', $ticket->id)
            ->where('event', ActivityEvent::Escalated)
            ->count();
        $this->assertSame(1, $activitiesCount);
    }

    public function test_resolved_and_closed_tickets_are_ignored(): void
    {
        Notification::fake();

        $now = Carbon::parse('2026-10-18 16:00:00', 'UTC');
        Carbon::setTestNow($now);

        // Resolved ticket with past due date
        $resolvedTicket = Ticket::create([
            'ticket_number' => 'TCK-RESOLVED-001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Resolved ticket check',
            'description' => 'Already solved',
            'priority' => Priority::High,
            'status' => TicketStatus::Resolved,
            'sla_warning_at' => $now->copy()->subHours(5),
            'sla_due_at' => $now->copy()->subHours(2),
            'sla_warning_notified_at' => null,
            'sla_breach_notified_at' => null,
            'resolved_at' => $now->copy()->subHours(3),
        ]);

        // Closed ticket with past due date
        $closedTicket = Ticket::create([
            'ticket_number' => 'TCK-CLOSED-001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Closed ticket check',
            'description' => 'Closed long ago',
            'priority' => Priority::Low,
            'status' => TicketStatus::Closed,
            'sla_warning_at' => $now->copy()->subDays(2),
            'sla_due_at' => $now->copy()->subDay(),
            'sla_warning_notified_at' => null,
            'sla_breach_notified_at' => null,
            'closed_at' => $now->copy()->subDay(),
        ]);

        $results = $this->service->checkAndEscalate($now);
        $this->assertSame(0, $results['breaches']);
        $this->assertSame(0, $results['warnings']);

        $resolvedTicket->refresh();
        $this->assertNull($resolvedTicket->escalated_at);
        $this->assertNull($resolvedTicket->sla_breach_notified_at);

        $closedTicket->refresh();
        $this->assertNull($closedTicket->escalated_at);
        $this->assertNull($closedTicket->sla_breach_notified_at);

        Notification::assertNothingSent();
    }

    public function test_unassigned_breached_ticket_notifies_all_admins(): void
    {
        Notification::fake();

        $now = Carbon::parse('2026-10-18 15:00:00', 'UTC');
        Carbon::setTestNow($now);

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-UNASSIGNED-BREACH',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => null, // Unassigned!
            'subject' => 'Unassigned ticket SLA breach',
            'description' => 'Nobody claimed this ticket',
            'priority' => Priority::Critical,
            'status' => TicketStatus::Open,
            'sla_warning_at' => $now->copy()->subHour(),
            'sla_due_at' => $now->copy()->subMinutes(10),
            'sla_warning_notified_at' => null,
            'sla_breach_notified_at' => null,
        ]);

        $results = $this->service->checkAndEscalate($now);
        $this->assertSame(1, $results['breaches']);

        // Since no agent was assigned, all admins receive notification
        Notification::assertSentTo($this->admin1, SlaBreachedNotification::class);
        Notification::assertSentTo($this->admin2, SlaBreachedNotification::class);
        Notification::assertNotSentTo($this->agent, SlaBreachedNotification::class);
    }

    public function test_check_sla_artisan_command_executes_successfully(): void
    {
        Notification::fake();

        $exitCode = Artisan::call('tickets:check-sla');
        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Completed SLA scan:', $output);
    }
}
