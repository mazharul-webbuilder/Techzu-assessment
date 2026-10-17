<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\Common\TicketFilter;
use App\Services\Common\TicketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketFilterTest extends TestCase
{
    use RefreshDatabase;

    protected TicketService $ticketService;
    protected TicketFilter $ticketFilter;

    protected Category $billingCat;
    protected Category $techCat;

    protected Customer $customerA;
    protected Customer $customerB;

    protected Agent $agent1;
    protected Agent $agent2;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ticketService = app(TicketService::class);
        $this->ticketFilter = app(TicketFilter::class);

        $this->billingCat = Category::create([
            'name' => 'Billing & Payments',
            'slug' => 'billing-payments',
            'is_active' => true,
        ]);

        $this->techCat = Category::create([
            'name' => 'Technical Support',
            'slug' => 'tech-support',
            'is_active' => true,
        ]);

        $this->customerA = Customer::create([
            'name' => 'Alice Customer',
            'email' => 'alice@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->customerB = Customer::create([
            'name' => 'Bob Customer',
            'email' => 'bob@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent1 = Agent::create([
            'name' => 'Agent One',
            'email' => 'agent1@support.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->agent2 = Agent::create([
            'name' => 'Agent Two',
            'email' => 'agent2@support.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->admin = Admin::create([
            'name' => 'Root Admin',
            'email' => 'admin@support.test',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_filter_by_ticket_number_prefix(): void
    {
        $ticket1 = Ticket::create([
            'ticket_number' => 'TKT-2026-000100',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'First Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        $ticket2 = Ticket::create([
            'ticket_number' => 'TKT-2025-000200',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Second Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        $results = $this->ticketService->getTicketsForActor($this->admin, [
            'ticket_number' => 'TKT-2026-',
        ]);

        $this->assertSame(1, $results->total());
        $this->assertSame($ticket1->id, $results->first()->id);
    }

    public function test_filter_by_keyword_on_subject_and_description(): void
    {
        $ticket1 = Ticket::create([
            'ticket_number' => 'TKT-SEARCH-1',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Database connection timeout',
            'description' => 'All workers failing',
            'status' => TicketStatus::Open,
            'priority' => Priority::High,
        ]);

        $ticket2 = Ticket::create([
            'ticket_number' => 'TKT-SEARCH-2',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->billingCat->id,
            'subject' => 'Invoice payment inquiry',
            'description' => 'Looking for receipt',
            'status' => TicketStatus::Open,
            'priority' => Priority::Low,
        ]);

        // Search subject keyword
        $subResults = $this->ticketService->getTicketsForActor($this->admin, ['search' => 'timeout']);
        $this->assertSame(1, $subResults->total());
        $this->assertSame($ticket1->id, $subResults->first()->id);

        // Search description keyword
        $descResults = $this->ticketService->getTicketsForActor($this->admin, ['keyword' => 'receipt']);
        $this->assertSame(1, $descResults->total());
        $this->assertSame($ticket2->id, $descResults->first()->id);
    }

    public function test_filter_by_status_and_active_grouping(): void
    {
        $openTicket = Ticket::create([
            'ticket_number' => 'TKT-STATUS-OPEN',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Open',
            'description' => 'Open ticket',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        $inProgressTicket = Ticket::create([
            'ticket_number' => 'TKT-STATUS-PROG',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'In Progress',
            'description' => 'In Progress ticket',
            'status' => TicketStatus::InProgress,
            'priority' => Priority::Medium,
        ]);

        $resolvedTicket = Ticket::create([
            'ticket_number' => 'TKT-STATUS-RES',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Resolved',
            'description' => 'Resolved ticket',
            'status' => TicketStatus::Resolved,
            'priority' => Priority::Medium,
        ]);

        // Exact status filter
        $openResults = $this->ticketService->getTicketsForActor($this->admin, ['status' => 'open']);
        $this->assertSame(1, $openResults->total());
        $this->assertSame($openTicket->id, $openResults->first()->id);

        // 'active' grouping filter (open + in_progress)
        $activeResults = $this->ticketService->getTicketsForActor($this->admin, ['status' => 'active']);
        $this->assertSame(2, $activeResults->total());
        $activeIds = $activeResults->pluck('id')->all();
        $this->assertContains($openTicket->id, $activeIds);
        $this->assertContains($inProgressTicket->id, $activeIds);
        $this->assertNotContains($resolvedTicket->id, $activeIds);
    }

    public function test_filter_by_priority_and_category(): void
    {
        $critBilling = Ticket::create([
            'ticket_number' => 'TKT-CRIT-BILL',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->billingCat->id,
            'subject' => 'Critical Billing',
            'description' => 'Payment gateway down',
            'status' => TicketStatus::Open,
            'priority' => Priority::Critical,
        ]);

        $lowTech = Ticket::create([
            'ticket_number' => 'TKT-LOW-TECH',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Low Tech',
            'description' => 'Minor CSS issue',
            'status' => TicketStatus::Open,
            'priority' => Priority::Low,
        ]);

        // Priority filter
        $critResults = $this->ticketService->getTicketsForActor($this->admin, ['priority' => 'critical']);
        $this->assertSame(1, $critResults->total());
        $this->assertSame($critBilling->id, $critResults->first()->id);

        // Category filter
        $techResults = $this->ticketService->getTicketsForActor($this->admin, ['category_id' => $this->techCat->id]);
        $this->assertSame(1, $techResults->total());
        $this->assertSame($lowTech->id, $techResults->first()->id);
    }

    public function test_filter_by_assigned_agent_and_unassigned(): void
    {
        $assigned1 = Ticket::create([
            'ticket_number' => 'TKT-AGENT-1',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'assigned_agent_id' => $this->agent1->id,
            'subject' => 'Assigned Agent 1',
            'description' => 'Work',
            'status' => TicketStatus::InProgress,
            'priority' => Priority::Medium,
        ]);

        $assigned2 = Ticket::create([
            'ticket_number' => 'TKT-AGENT-2',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'assigned_agent_id' => $this->agent2->id,
            'subject' => 'Assigned Agent 2',
            'description' => 'Work',
            'status' => TicketStatus::InProgress,
            'priority' => Priority::Medium,
        ]);

        $unassigned = Ticket::create([
            'ticket_number' => 'TKT-UNASSIGNED',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'assigned_agent_id' => null,
            'subject' => 'Unassigned',
            'description' => 'Work',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        // Filter by Agent 1
        $agent1Results = $this->ticketService->getTicketsForActor($this->admin, ['assigned_agent' => $this->agent1->id]);
        $this->assertSame(1, $agent1Results->total());
        $this->assertSame($assigned1->id, $agent1Results->first()->id);

        // Filter by 'unassigned'
        $unassignedResults = $this->ticketService->getTicketsForActor($this->admin, ['assigned_agent' => 'unassigned']);
        $this->assertSame(1, $unassignedResults->total());
        $this->assertSame($unassigned->id, $unassignedResults->first()->id);
    }

    public function test_filter_by_created_date_range(): void
    {
        Carbon::setTestNow('2026-10-10 10:00:00');
        $oct10 = Ticket::create([
            'ticket_number' => 'TKT-OCT-10',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Oct 10 Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        Carbon::setTestNow('2026-10-15 10:00:00');
        $oct15 = Ticket::create([
            'ticket_number' => 'TKT-OCT-15',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Oct 15 Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        Carbon::setTestNow('2026-10-20 10:00:00');
        $oct20 = Ticket::create([
            'ticket_number' => 'TKT-OCT-20',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Oct 20 Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        // Range 2026-10-12 to 2026-10-18 -> only Oct 15
        $results = $this->ticketService->getTicketsForActor($this->admin, [
            'date_from' => '2026-10-12',
            'date_to' => '2026-10-18',
        ]);

        $this->assertSame(1, $results->total());
        $this->assertSame($oct15->id, $results->first()->id);
    }

    public function test_customer_filter_never_widens_visibility_scope_when_requesting_other_customer_id(): void
    {
        // Ticket belonging to Customer A
        $ticketA = Ticket::create([
            'ticket_number' => 'TKT-ALICE-1',
            'customer_id' => $this->customerA->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Alice Secret Issue',
            'description' => 'Private',
            'status' => TicketStatus::Open,
            'priority' => Priority::High,
        ]);

        // Ticket belonging to Customer B
        $ticketB = Ticket::create([
            'ticket_number' => 'TKT-BOB-1',
            'customer_id' => $this->customerB->id,
            'category_id' => $this->techCat->id,
            'subject' => 'Bob Secret Issue',
            'description' => 'Private',
            'status' => TicketStatus::Open,
            'priority' => Priority::High,
        ]);

        // Customer A queries passing customer_id = Bob's ID
        $response = $this->actingAs($this->customerA, 'customer')
            ->get(route('customer.tickets.index', ['customer_id' => $this->customerB->id]));

        $response->assertStatus(200);

        // Security assertion: Alice must NEVER see Bob's ticket, even if she passed Bob's ID in the query string
        $response->assertDontSee('Bob Secret Issue');
        $response->assertDontSee('TKT-BOB-1');
    }

    public function test_query_count_stays_constant_with_thirty_tickets_no_n_plus_one(): void
    {
        // Seed 30 tickets
        for ($i = 1; $i <= 30; $i++) {
            Ticket::create([
                'ticket_number' => sprintf('TKT-NPLUS1-%03d', $i),
                'customer_id' => ($i % 2 === 0) ? $this->customerA->id : $this->customerB->id,
                'category_id' => ($i % 2 === 0) ? $this->techCat->id : $this->billingCat->id,
                'assigned_agent_id' => ($i % 3 === 0) ? $this->agent1->id : (($i % 3 === 1) ? $this->agent2->id : null),
                'subject' => "Ticket Subject {$i}",
                'description' => "Ticket Description {$i}",
                'status' => TicketStatus::Open,
                'priority' => Priority::Medium,
                'sla_due_at' => now()->addHours(8),
                'sla_warning_at' => now()->addHours(6),
            ]);
        }

        // Measure query count for 30 tickets on admin ticket list
        $queryLog = [];
        DB::listen(function ($query) use (&$queryLog) {
            $queryLog[] = $query->sql;
        });

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tickets.index'));

        // Query breakdown:
        // 1. Session / Auth check
        // 2. Count query for pagination
        // 3. Select 15 tickets query
        // 4. Eager load customers (in (id1, id2))
        // 5. Eager load categories (in (id1, id2))
        // 6. Eager load assigned agents (in (id1, id2))
        // 7. Active categories query for filter dropdown
        // 8. Agents with workload query for filter dropdown
        // Total queries should be <= 8. Crucially: NEVER 30+ separate N+1 queries!
        $this->assertLessThanOrEqual(8, count($queryLog), 'N+1 detected: Too many database queries executed on ticket index page.');
    }
}
