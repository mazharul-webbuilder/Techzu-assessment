<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\SlaStatus;
use App\Enums\TicketStatus;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\Common\SlaService;
use App\Services\Common\TicketService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SlaService $slaService;
    protected TicketService $ticketService;
    protected Customer $customer;
    protected Agent $agent;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slaService = app(SlaService::class);
        $this->ticketService = app(TicketService::class);

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@sla.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent = Agent::create([
            'name' => 'Test Agent',
            'email' => 'agent@sla.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);
    }

    public function test_sla_service_deadlines_for_priorities_with_eighty_percent_warning(): void
    {
        // Use a clean Sunday 10:00 Asia/Dhaka start time (all fits in one day)
        $start = CarbonImmutable::parse('2026-10-18 10:00:00', 'Asia/Dhaka');

        // Critical: 120 min due, 96 min warning (120 * 0.8 = 96)
        $critical = $this->slaService->deadlinesFor(Priority::Critical, $start);
        $this->assertSame(
            '2026-10-18 12:00:00',
            $critical['sla_due_at']->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-10-18 11:36:00',
            $critical['sla_warning_at']->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s')
        );

        // High: 480 min due, 384 min warning (480 * 0.8 = 384)
        $high = $this->slaService->deadlinesFor(Priority::High, $start);
        $this->assertSame(
            '2026-10-18 18:00:00',
            $high['sla_due_at']->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-10-18 16:24:00',
            $high['sla_warning_at']->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s')
        );
    }

    public function test_priority_change_recalculates_sla_from_original_created_at_time(): void
    {
        // Created on Sunday 2026-10-18 at 10:00 Asia/Dhaka with Medium priority
        $createdAt = CarbonImmutable::parse('2026-10-18 10:00:00', 'Asia/Dhaka')->utc();
        Carbon::setTestNow($createdAt);

        $ticket = Ticket::create([
            'ticket_number' => 'TCK-SLA-001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'SLA Recalc Test',
            'description' => 'Testing recalculation from created_at',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
            'sla_due_at' => $createdAt->addMinutes(1440),
            'sla_warning_at' => $createdAt->addMinutes(1152),
            'sla_warning_notified_at' => now(),
            'sla_breach_notified_at' => null,
        ]);

        // 3 hours later (13:00 Asia/Dhaka), priority is escalated to Critical (120 min)
        $escalationTime = Carbon::parse('2026-10-18 13:00:00', 'Asia/Dhaka');
        Carbon::setTestNow($escalationTime);

        $this->ticketService->changePriority($ticket, Priority::Critical, $this->agent);
        $ticket->refresh();

        $this->assertSame(Priority::Critical, $ticket->priority);
        $this->assertNull($ticket->sla_warning_notified_at);
        $this->assertNull($ticket->sla_breach_notified_at);

        // Due time should be based on created_at (10:00 Asia/Dhaka + 120 min = 12:00 Asia/Dhaka)
        $expectedDue = CarbonImmutable::parse('2026-10-18 12:00:00', 'Asia/Dhaka')->utc();
        $this->assertEquals($expectedDue->timestamp, $ticket->sla_due_at->timestamp);

        // Since current time is 13:00 and due was 12:00, it is immediately breached!
        $this->assertSame(SlaStatus::Breached, $ticket->slaStatus());
    }

    public function test_ticket_sla_scopes_and_status_accessor(): void
    {
        $base = Carbon::parse('2026-10-18 12:00:00', 'UTC');
        Carbon::setTestNow($base);

        // 1. Healthy ticket: warning is in future (e.g. +2 hours)
        $withinTicket = Ticket::create([
            'ticket_number' => 'TCK-WITHIN',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Within SLA',
            'description' => 'Healthy',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
            'sla_warning_at' => $base->copy()->addHours(2),
            'sla_due_at' => $base->copy()->addHours(4),
        ]);

        // 2. Approaching ticket: warning passed (-1 hour) but due in future (+1 hour)
        $approachingTicket = Ticket::create([
            'ticket_number' => 'TCK-APPROACH',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Approaching Breach',
            'description' => 'Warning active',
            'status' => TicketStatus::InProgress,
            'priority' => Priority::High,
            'sla_warning_at' => $base->copy()->subHours(1),
            'sla_due_at' => $base->copy()->addHours(1),
        ]);

        // 3. Breached ticket: due passed (-30 min)
        $breachedTicket = Ticket::create([
            'ticket_number' => 'TCK-BREACHED',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Breached SLA',
            'description' => 'Deadline passed',
            'status' => TicketStatus::Open,
            'priority' => Priority::Critical,
            'sla_warning_at' => $base->copy()->subHours(2),
            'sla_due_at' => $base->copy()->subMinutes(30),
        ]);

        // 4. Resolved ticket: even if due passed, it is resolved so SLA status is null
        $resolvedTicket = Ticket::create([
            'ticket_number' => 'TCK-RESOLVED',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Resolved Ticket',
            'description' => 'Done',
            'status' => TicketStatus::Resolved,
            'priority' => Priority::Critical,
            'sla_warning_at' => $base->copy()->subHours(5),
            'sla_due_at' => $base->copy()->subHours(3),
        ]);

        // Test Scopes
        $withinIds = Ticket::slaWithin()->pluck('id')->all();
        $this->assertContains($withinTicket->id, $withinIds);
        $this->assertNotContains($approachingTicket->id, $withinIds);
        $this->assertNotContains($breachedTicket->id, $withinIds);
        $this->assertNotContains($resolvedTicket->id, $withinIds);

        $approachingIds = Ticket::slaApproaching()->pluck('id')->all();
        $this->assertContains($approachingTicket->id, $approachingIds);
        $this->assertNotContains($withinTicket->id, $approachingIds);
        $this->assertNotContains($breachedTicket->id, $approachingIds);
        $this->assertNotContains($resolvedTicket->id, $approachingIds);

        $breachedIds = Ticket::slaBreached()->pluck('id')->all();
        $this->assertContains($breachedTicket->id, $breachedIds);
        $this->assertNotContains($withinTicket->id, $breachedIds);
        $this->assertNotContains($approachingTicket->id, $breachedIds);
        $this->assertNotContains($resolvedTicket->id, $breachedIds);

        // Test Accessor
        $this->assertSame(SlaStatus::Within, $withinTicket->slaStatus());
        $this->assertSame(SlaStatus::Approaching, $approachingTicket->slaStatus());
        $this->assertSame(SlaStatus::Breached, $breachedTicket->slaStatus());
        $this->assertNull($resolvedTicket->slaStatus());
    }
}
