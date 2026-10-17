<?php

namespace Tests\Feature;

use App\Enums\ActivityEvent;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Jobs\AutoAssignTicketJob;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Services\Common\TicketAssignmentService;
use App\Services\Common\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected Customer $customer;
    protected Admin $admin;
    protected Agent $agent1;
    protected Agent $agent2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'John Customer',
            'email' => 'john@customer.test',
            'password' => bcrypt('password'),
        ]);

        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@system.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent1 = Agent::create([
            'name' => 'Agent One',
            'email' => 'agent1@system.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->agent2 = Agent::create([
            'name' => 'Agent Two',
            'email' => 'agent2@system.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);
    }

    public function test_agent_can_claim_unassigned_ticket_and_status_changes_to_in_progress(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Unassigned Server Outage',
            'description' => 'Server is unresponsive.',
            'priority' => Priority::High,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
        ]);

        $response = $this->actingAs($this->agent1, 'agent')
            ->post(route('agent.tickets.claim', $ticket));

        $response->assertSessionHas('success');
        $ticket->refresh();

        $this->assertEquals($this->agent1->id, $ticket->assigned_agent_id);
        $this->assertEquals(TicketStatus::InProgress, $ticket->status);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'actor_type' => $this->agent1->getMorphClass(),
            'actor_id' => $this->agent1->id,
            'event' => ActivityEvent::Assigned->value,
        ]);
    }

    /**
     * Concurrency Solution Verification:
     * When two agents attempt to claim the exact same ticket at the exact same time,
     * the database-level conditional UPDATE (WHERE assigned_agent_id IS NULL)
     * ensures only the first agent succeeds (1 row affected), while the second
     * agent affects 0 rows, receives TicketAlreadyAssignedException (HTTP 409),
     * and the ticket is never double-assigned.
     */
    public function test_concurrent_claim_race_condition_serializes_and_throws_ticket_already_assigned_exception(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000002',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Payment Gateway Error',
            'description' => 'Charges failing.',
            'priority' => Priority::Critical,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
        ]);

        $assignmentService = app(TicketAssignmentService::class);

        // Agent 1 claims first -> succeeds
        $claimedTicket = $assignmentService->claim($ticket, $this->agent1);
        $this->assertEquals($this->agent1->id, $claimedTicket->assigned_agent_id);

        // Agent 2 attempts to claim the same ticket concurrently via service (simulating race condition)
        try {
            $assignmentService->claim($ticket, $this->agent2);
            $this->fail('Expected TicketAlreadyAssignedException was not thrown on concurrent claim.');
        } catch (TicketAlreadyAssignedException $e) {
            // Verify HTTP 409 response for JSON clients
            $jsonRequest = \Illuminate\Http\Request::create('/agent/tickets/' . $ticket->id . '/claim', 'POST');
            $jsonRequest->headers->set('Accept', 'application/json');
            $response = $e->render($jsonRequest);
            $this->assertEquals(409, $response->getStatusCode());

            // Verify redirect with flash error for web browser clients
            $webRequest = \Illuminate\Http\Request::create('/agent/tickets/' . $ticket->id . '/claim', 'POST');
            $webResponse = $e->render($webRequest);
            $this->assertEquals(302, $webResponse->getStatusCode());
        }

        // Verify the original agent remains assigned and ticket was NOT overwritten
        $this->assertEquals($this->agent1->id, $ticket->fresh()->assigned_agent_id);

        // Verify only 1 assignment activity was logged (no duplicate assignments)
        $this->assertEquals(1, TicketActivity::where('ticket_id', $ticket->id)->where('event', ActivityEvent::Assigned)->count());
    }

    public function test_claiming_already_assigned_ticket_via_controller_is_forbidden_by_policy(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000009',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Already Claimed Ticket',
            'description' => 'Already taken.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
            'assigned_agent_id' => $this->agent1->id,
        ]);

        // Agent 2 tries to claim a ticket that is already assigned to Agent 1 -> Policy forbids (403)
        $response = $this->actingAs($this->agent2, 'agent')
            ->post(route('agent.tickets.claim', $ticket));

        $response->assertForbidden();
    }

    public function test_admin_can_manually_assign_and_reassign_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000003',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Database Performance Query',
            'description' => 'Slow queries on checkout.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
        ]);

        // Admin assigns to Agent 1
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.tickets.assign', $ticket), [
                'agent_id' => $this->agent1->id,
            ]);

        $response->assertSessionHas('success');
        $ticket->refresh();

        $this->assertEquals($this->agent1->id, $ticket->assigned_agent_id);
        $this->assertEquals(TicketStatus::InProgress, $ticket->status);

        // Admin reassigns to Agent 2
        $reassignResponse = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.tickets.assign', $ticket), [
                'agent_id' => $this->agent2->id,
            ]);

        $reassignResponse->assertSessionHas('success');
        $ticket->refresh();

        $this->assertEquals($this->agent2->id, $ticket->assigned_agent_id);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'actor_type' => $this->admin->getMorphClass(),
            'actor_id' => $this->admin->id,
            'event' => ActivityEvent::Reassigned->value,
        ]);
    }

    public function test_workload_strategy_picks_least_loaded_available_agent(): void
    {
        // Give Agent 1 two active tickets
        Ticket::create([
            'ticket_number' => 'TKT-2026-000010',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Task 1',
            'description' => 'desc',
            'priority' => Priority::Low,
            'status' => TicketStatus::InProgress,
            'assigned_agent_id' => $this->agent1->id,
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-2026-000011',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Task 2',
            'description' => 'desc',
            'priority' => Priority::Low,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => $this->agent1->id,
        ]);

        // Agent 2 has 0 active tickets -> pickAgent must choose Agent 2
        $assignmentService = app(TicketAssignmentService::class);
        $picked = $assignmentService->pickAgent();

        $this->assertNotNull($picked);
        $this->assertEquals($this->agent2->id, $picked->id);

        // Mark Agent 2 as unavailable (e.g. on leave) -> pickAgent must fall back to Agent 1
        $this->agent2->update(['is_available' => false]);

        $pickedAfterUnavailable = $assignmentService->pickAgent();
        $this->assertNotNull($pickedAfterUnavailable);
        $this->assertEquals($this->agent1->id, $pickedAfterUnavailable->id);
    }

    public function test_auto_assign_via_admin_controller(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000004',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'SSL Expiration',
            'description' => 'Cert expiring in 2 days.',
            'priority' => Priority::High,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.tickets.auto-assign', $ticket));

        $response->assertSessionHas('success');
        $ticket->refresh();

        $this->assertNotNull($ticket->assigned_agent_id);
        $this->assertEquals(TicketStatus::InProgress, $ticket->status);
    }

    public function test_customer_and_agents_cannot_assign_tickets_to_others(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000005',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Restricted Action',
            'description' => 'Testing forbidden assignment.',
            'priority' => Priority::Low,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
        ]);

        // Customer attempts admin assign route
        $customerResponse = $this->actingAs($this->customer, 'customer')
            ->post(route('admin.tickets.assign', $ticket), [
                'agent_id' => $this->agent1->id,
            ]);
        $customerResponse->assertRedirect(route('admin.login'));

        // Agent attempts admin assign route
        $agentResponse = $this->actingAs($this->agent1, 'agent')
            ->post(route('admin.tickets.assign', $ticket), [
                'agent_id' => $this->agent2->id,
            ]);
        $agentResponse->assertRedirect(route('admin.login'));
    }

    public function test_agent_can_toggle_availability_status(): void
    {
        $this->assertTrue($this->agent1->is_available);

        $response = $this->actingAs($this->agent1, 'agent')
            ->post(route('agent.availability.toggle'));

        $response->assertSessionHas('success');
        $this->agent1->refresh();
        $this->assertFalse($this->agent1->is_available);

        // Toggle back to available
        $this->actingAs($this->agent1, 'agent')
            ->post(route('agent.availability.toggle'));

        $this->agent1->refresh();
        $this->assertTrue($this->agent1->is_available);
    }

    public function test_auto_assign_job_dispatches_when_customer_creates_ticket(): void
    {
        Queue::fake();

        $ticketService = app(TicketService::class);
        $ticket = $ticketService->create($this->customer, [
            'category_id' => $this->category->id,
            'subject' => 'Queued Assignment Test',
            'description' => 'Testing AutoAssignTicketJob dispatch.',
            'priority' => Priority::Medium->value,
        ]);

        Queue::assertPushed(AutoAssignTicketJob::class, function ($job) use ($ticket) {
            return $job->ticket->id === $ticket->id;
        });
    }

    public function test_agent_workstation_tab_filters_assigned_and_unassigned_pool(): void
    {
        // 1 ticket assigned to Agent 1
        $myTicket = Ticket::create([
            'ticket_number' => 'TKT-2026-000077',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'My Assigned Ticket',
            'description' => 'Mine.',
            'priority' => Priority::Low,
            'status' => TicketStatus::InProgress,
            'assigned_agent_id' => $this->agent1->id,
        ]);

        // 1 unassigned ticket
        $unassignedTicket = Ticket::create([
            'ticket_number' => 'TKT-2026-000078',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Unassigned Ticket In Pool',
            'description' => 'Waiting for claim.',
            'priority' => Priority::Low,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
        ]);

        // Tab: My Assigned -> sees My Ticket, does NOT see Unassigned
        $myResponse = $this->actingAs($this->agent1, 'agent')
            ->get(route('agent.tickets.index', ['tab' => 'my']));
        $myResponse->assertOk();
        $myResponse->assertSee($myTicket->ticket_number);
        $myResponse->assertDontSee($unassignedTicket->ticket_number);

        // Tab: Unassigned Pool -> sees Unassigned, does NOT see My Ticket
        $unassignedResponse = $this->actingAs($this->agent1, 'agent')
            ->get(route('agent.tickets.index', ['tab' => 'unassigned']));
        $unassignedResponse->assertOk();
        $unassignedResponse->assertSee($unassignedTicket->ticket_number);
        $unassignedResponse->assertDontSee($myTicket->ticket_number);

        // Tab: All Queue -> sees both
        $allResponse = $this->actingAs($this->agent1, 'agent')
            ->get(route('agent.tickets.index', ['tab' => 'all']));
        $allResponse->assertOk();
        $allResponse->assertSee($myTicket->ticket_number);
        $allResponse->assertSee($unassignedTicket->ticket_number);
    }
}
