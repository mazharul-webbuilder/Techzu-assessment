<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customerA;
    protected Customer $customerB;
    protected Agent $agent1;
    protected Agent $agent2;
    protected Admin $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = Customer::create([
            'name' => 'Customer Alice',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->customerB = Customer::create([
            'name' => 'Customer Bob',
            'email' => 'bob@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->agent1 = Agent::create([
            'name' => 'Agent One',
            'email' => 'agent1@example.com',
            'password' => Hash::make('password'),
            'is_available' => true,
        ]);

        $this->agent2 = Agent::create([
            'name' => 'Agent Two',
            'email' => 'agent2@example.com',
            'password' => Hash::make('password'),
            'is_available' => true,
        ]);

        $this->admin = Admin::create([
            'name' => 'Admin Boss',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->category = Category::create([
            'name' => 'Billing Support',
            'is_active' => true,
        ]);
    }

    /**
     * Test a customer can view their own ticket.
     */
    public function test_customer_can_view_own_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000001',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->category->id,
            'subject' => 'Alice Inquiry',
            'description' => 'Alice needs help with billing.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        $this->actingAs($this->customerA, 'customer')
            ->get(route('customer.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Alice Inquiry');
    }

    /**
     * Test customer A cannot open customer B's ticket (returns 403 Forbidden).
     */
    public function test_customer_cannot_open_another_customers_ticket(): void
    {
        $ticketOfB = Ticket::create([
            'ticket_number' => 'TKT-2026-000002',
            'customer_id' => $this->customerB->id,
            'category_id' => $this->category->id,
            'subject' => 'Bob Private Ticket',
            'description' => 'Bob private issue.',
            'priority' => Priority::High,
            'status' => TicketStatus::Open,
        ]);

        // Alice tries to open Bob's ticket
        $this->actingAs($this->customerA, 'customer')
            ->get(route('customer.tickets.show', $ticketOfB))
            ->assertStatus(403);
    }

    /**
     * Test customer ticket listing never displays other customers' tickets.
     */
    public function test_customer_cannot_list_other_customers_tickets(): void
    {
        Ticket::create([
            'ticket_number' => 'TKT-2026-000001',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->category->id,
            'subject' => 'Alice Own Ticket',
            'description' => 'Alice description',
            'priority' => Priority::Low,
            'status' => TicketStatus::Open,
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-2026-000002',
            'customer_id' => $this->customerB->id,
            'category_id' => $this->category->id,
            'subject' => 'Bob Secret Ticket',
            'description' => 'Bob description',
            'priority' => Priority::High,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($this->customerA, 'customer')
            ->get(route('customer.tickets.index'));

        $response->assertOk();
        $response->assertSee('Alice Own Ticket');
        $response->assertDontSee('Bob Secret Ticket');
    }

    /**
     * Test agent cannot view a ticket assigned to another agent.
     */
    public function test_agent_cannot_view_ticket_assigned_to_another_agent(): void
    {
        $ticketAssignedToAgent2 = Ticket::create([
            'ticket_number' => 'TKT-2026-000003',
            'customer_id' => $this->customerA->id,
            'assigned_agent_id' => $this->agent2->id,
            'category_id' => $this->category->id,
            'subject' => 'Assigned to Agent 2',
            'description' => 'Handled by agent 2.',
            'priority' => Priority::High,
            'status' => TicketStatus::InProgress,
        ]);

        // Agent 1 tries to view ticket assigned to Agent 2
        $this->actingAs($this->agent1, 'agent')
            ->get(route('agent.tickets.show', $ticketAssignedToAgent2))
            ->assertStatus(403);
    }

    /**
     * Test agent can view tickets assigned to themselves.
     */
    public function test_agent_can_view_own_assigned_ticket(): void
    {
        $ticketAssignedToAgent1 = Ticket::create([
            'ticket_number' => 'TKT-2026-000004',
            'customer_id' => $this->customerA->id,
            'assigned_agent_id' => $this->agent1->id,
            'category_id' => $this->category->id,
            'subject' => 'Assigned to Agent 1',
            'description' => 'Handled by agent 1.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
        ]);

        $this->actingAs($this->agent1, 'agent')
            ->get(route('agent.tickets.show', $ticketAssignedToAgent1))
            ->assertOk()
            ->assertSee('Assigned to Agent 1');
    }

    /**
     * Test agent can view unassigned tickets in the pool.
     */
    public function test_agent_can_view_unassigned_pool_ticket(): void
    {
        $unassignedTicket = Ticket::create([
            'ticket_number' => 'TKT-2026-000005',
            'customer_id' => $this->customerA->id,
            'assigned_agent_id' => null,
            'category_id' => $this->category->id,
            'subject' => 'Unassigned Triage Ticket',
            'description' => 'Waiting in queue.',
            'priority' => Priority::Critical,
            'status' => TicketStatus::Open,
        ]);

        $this->actingAs($this->agent1, 'agent')
            ->get(route('agent.tickets.show', $unassignedTicket))
            ->assertOk()
            ->assertSee('Unassigned Triage Ticket');
    }

    /**
     * Test admin can view any ticket regardless of customer or assigned agent.
     */
    public function test_admin_can_view_any_ticket(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000006',
            'customer_id' => $this->customerB->id,
            'assigned_agent_id' => $this->agent2->id,
            'category_id' => $this->category->id,
            'subject' => 'Global Audit Ticket',
            'description' => 'Admin can oversee all tickets.',
            'priority' => Priority::Low,
            'status' => TicketStatus::Open,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Global Audit Ticket');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tickets.index'))
            ->assertOk()
            ->assertSee('Global Audit Ticket');
    }

    /**
     * Test customers cannot view internal notes at the query level.
     */
    public function test_customer_cannot_see_internal_notes(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000007',
            'customer_id' => $this->customerA->id,
            'assigned_agent_id' => $this->agent1->id,
            'category_id' => $this->category->id,
            'subject' => 'Internal Note Test Ticket',
            'description' => 'Testing note visibility',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'author_type' => 'agent',
            'author_id' => $this->agent1->id,
            'body' => 'Public reply visible to customer',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'author_type' => 'agent',
            'author_id' => $this->agent1->id,
            'body' => 'CONFIDENTIAL_INTERNAL_STAFF_NOTE_XYZ',
            'is_internal' => true,
        ]);

        // Customer views ticket: sees public reply, never sees internal note
        $customerResponse = $this->actingAs($this->customerA, 'customer')
            ->get(route('customer.tickets.show', $ticket));

        $customerResponse->assertOk();
        $customerResponse->assertSee('Public reply visible to customer');
        $customerResponse->assertDontSee('CONFIDENTIAL_INTERNAL_STAFF_NOTE_XYZ');

        // Staff Agent views ticket: sees BOTH public reply and internal note
        $agentResponse = $this->actingAs($this->agent1, 'agent')
            ->get(route('agent.tickets.show', $ticket));

        $agentResponse->assertOk();
        $agentResponse->assertSee('Public reply visible to customer');
        $agentResponse->assertSee('CONFIDENTIAL_INTERNAL_STAFF_NOTE_XYZ');

        // Admin views ticket: sees BOTH public reply and internal note
        $adminResponse = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tickets.show', $ticket));

        $adminResponse->assertOk();
        $adminResponse->assertSee('Public reply visible to customer');
        $adminResponse->assertSee('CONFIDENTIAL_INTERNAL_STAFF_NOTE_XYZ');
    }
}
