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
use App\Models\TicketReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketConversationTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected Customer $customer;
    protected Customer $otherCustomer;
    protected Admin $admin;
    protected Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Alice Customer',
            'email' => 'alice@customer.test',
            'password' => bcrypt('password'),
        ]);

        $this->otherCustomer = Customer::create([
            'name' => 'Bob Customer',
            'email' => 'bob@customer.test',
            'password' => bcrypt('password'),
        ]);

        $this->admin = Admin::create([
            'name' => 'Site Admin',
            'email' => 'admin@system.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent = Agent::create([
            'name' => 'Agent Smith',
            'email' => 'smith@system.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);
    }

    public function test_customer_can_post_public_reply(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000001',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Issue with VPN',
            'description' => 'Cannot connect to server.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.reply', $ticket), [
                'body' => 'Here is my update regarding the VPN connection.',
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'body' => 'Here is my update regarding the VPN connection.',
            'is_internal' => false,
            'author_type' => $this->customer->getMorphClass(),
            'author_id' => $this->customer->id,
        ]);

        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event' => ActivityEvent::Replied->value,
            'actor_id' => $this->customer->id,
        ]);
    }

    public function test_customer_cannot_post_internal_note_even_by_forging_is_internal(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000002',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Forged Internal Note Attempt',
            'description' => 'Testing customer forging is_internal=1',
            'priority' => Priority::Low,
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.reply', $ticket), [
                'body' => 'I am trying to pose as internal staff note.',
                'is_internal' => 1, // Forged parameter
            ]);

        $response->assertSessionHas('success');

        // Verify strictly stored as is_internal = false
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'body' => 'I am trying to pose as internal staff note.',
            'is_internal' => false,
        ]);

        $this->assertDatabaseMissing('ticket_activities', [
            'ticket_id' => $ticket->id,
            'event' => ActivityEvent::InternalNoteAdded->value,
        ]);
    }

    public function test_staff_agent_can_post_internal_note_and_customer_never_sees_it_in_html(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000003',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Staff Secret Internal Discussion',
            'description' => 'Public customer inquiry.',
            'priority' => Priority::High,
            'status' => TicketStatus::InProgress,
        ]);

        $secretNoteText = 'SECRET-STAFF-NOTE-CONFIDENTIAL-INVESTIGATION-123';

        // Agent posts internal note
        $agentResponse = $this->actingAs($this->agent, 'agent')
            ->post(route('agent.tickets.reply', $ticket), [
                'body' => $secretNoteText,
                'is_internal' => 1,
            ]);

        $agentResponse->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'body' => $secretNoteText,
            'is_internal' => true,
        ]);

        // 1. Agent views page -> MUST see the internal note
        $agentView = $this->actingAs($this->agent, 'agent')
            ->get(route('agent.tickets.show', $ticket));
        $agentView->assertOk();
        $agentView->assertSee($secretNoteText);

        // 2. Customer views page -> MUST NEVER see the internal note in HTML
        $customerView = $this->actingAs($this->customer, 'customer')
            ->get(route('customer.tickets.show', $ticket));
        $customerView->assertOk();
        $customerView->assertDontSee($secretNoteText);
    }

    public function test_customer_reply_on_waiting_for_customer_moves_ticket_to_in_progress(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000004',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Awaiting Info',
            'description' => 'Waiting for user info.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::WaitingForCustomer,
        ]);

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.reply', $ticket), [
                'body' => 'Here is the requested information.',
            ]);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::InProgress, $ticket->status);

        $activity = TicketActivity::where('ticket_id', $ticket->id)
            ->where('event', ActivityEvent::StatusChanged)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals(['status' => TicketStatus::WaitingForCustomer->value], $activity->old_values);
        $this->assertEquals(['status' => TicketStatus::InProgress->value], $activity->new_values);
    }

    public function test_customer_reply_on_resolved_ticket_reopens_it(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000005',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Resolved issue',
            'description' => 'Was resolved.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.reply', $ticket), [
                'body' => 'Actually the issue occurred again!',
            ]);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_customer_can_close_own_resolved_ticket_but_not_unresolved_ticket(): void
    {
        $unresolvedTicket = Ticket::create([
            'ticket_number' => 'TKT-2026-000006',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Open ticket',
            'description' => 'Open.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        // Customer attempts to close open ticket -> 403 Forbidden
        $forbiddenResponse = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.close', $unresolvedTicket));
        $forbiddenResponse->assertForbidden();

        // Customer closes resolved ticket -> succeeds
        $resolvedTicket = Ticket::create([
            'ticket_number' => 'TKT-2026-000007',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'subject' => 'Resolved ticket',
            'description' => 'Resolved.',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $successResponse = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.close', $resolvedTicket));
        $successResponse->assertSessionHas('success');

        $resolvedTicket->refresh();
        $this->assertEquals(TicketStatus::Closed, $resolvedTicket->status);
        $this->assertNotNull($resolvedTicket->closed_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000008',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Open ticket',
            'description' => 'desc',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        // Attempting direct jump Open -> Closed (invalid transition according to state machine)
        $response = $this->actingAs($this->agent, 'agent')
            ->post(route('agent.tickets.status', $ticket), [
                'status' => TicketStatus::Closed->value,
            ]);

        $response->assertSessionHas('error');
        $ticket->refresh();
        $this->assertEquals(TicketStatus::Open, $ticket->status);
    }

    public function test_priority_change_recalculates_deadlines_and_resets_notified_flags(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-000010',
            'customer_id' => $this->customer->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Priority Upgrade',
            'description' => 'Upgrading to Critical.',
            'priority' => Priority::Low,
            'status' => TicketStatus::InProgress,
            'sla_warning_notified_at' => now(),
            'sla_breach_notified_at' => now(),
        ]);

        $response = $this->actingAs($this->agent, 'agent')
            ->post(route('agent.tickets.priority', $ticket), [
                'priority' => Priority::Critical->value,
            ]);

        $response->assertSessionHas('success');
        $ticket->refresh();

        $this->assertEquals(Priority::Critical, $ticket->priority);
        $this->assertNull($ticket->sla_warning_notified_at);
        $this->assertNull($ticket->sla_breach_notified_at);

        $activity = TicketActivity::where('ticket_id', $ticket->id)
            ->where('event', ActivityEvent::PriorityChanged)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals(['priority' => Priority::Low->value], $activity->old_values);
        $this->assertEquals(['priority' => Priority::Critical->value], $activity->new_values);
    }
}
