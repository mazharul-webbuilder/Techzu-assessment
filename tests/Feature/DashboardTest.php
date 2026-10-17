<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\Admin\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Agent $agent1;
    protected Agent $agent2;
    protected Customer $customer1;
    protected Customer $customer2;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Admin User',
            'email' => 'admin@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent1 = Agent::create([
            'name' => 'Agent One',
            'email' => 'agent1@test.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->agent2 = Agent::create([
            'name' => 'Agent Two',
            'email' => 'agent2@test.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->customer1 = Customer::create([
            'name' => 'Customer One',
            'email' => 'customer1@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->customer2 = Customer::create([
            'name' => 'Customer Two',
            'email' => 'customer2@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->category = Category::create([
            'name' => 'Billing',
            'is_active' => true,
        ]);
    }

    public function test_admin_dashboard_aggregates_kpis_accurately(): void
    {
        $now = Carbon::now();

        // 1. Unassigned, Critical, Open
        Ticket::create([
            'ticket_number' => 'TKT-2026-000001',
            'customer_id' => $this->customer1->id,
            'category_id' => $this->category->id,
            'subject' => 'Critical Unassigned',
            'description' => 'Help immediately',
            'priority' => Priority::Critical,
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
            'sla_due_at' => $now->copy()->addHours(2),
            'sla_warning_at' => $now->copy()->addHour(),
        ]);

        // 2. Assigned to Agent 1, Breached SLA, In Progress
        Ticket::create([
            'ticket_number' => 'TKT-2026-000002',
            'customer_id' => $this->customer1->id,
            'category_id' => $this->category->id,
            'subject' => 'Breached Ticket',
            'description' => 'Overdue ticket',
            'priority' => Priority::High,
            'status' => TicketStatus::InProgress,
            'assigned_agent_id' => $this->agent1->id,
            'sla_due_at' => $now->copy()->subMinutes(30),
            'sla_warning_at' => $now->copy()->subHours(2),
        ]);

        // 3. Assigned to Agent 2, Approaching SLA, In Progress
        Ticket::create([
            'ticket_number' => 'TKT-2026-000003',
            'customer_id' => $this->customer2->id,
            'category_id' => $this->category->id,
            'subject' => 'Approaching Ticket',
            'description' => 'Warning breached but not due',
            'priority' => Priority::Medium,
            'status' => TicketStatus::InProgress,
            'assigned_agent_id' => $this->agent2->id,
            'sla_due_at' => $now->copy()->addMinutes(30),
            'sla_warning_at' => $now->copy()->subMinutes(10),
        ]);

        // 4. Assigned to Agent 1, Waiting For Customer, Low
        Ticket::create([
            'ticket_number' => 'TKT-2026-000004',
            'customer_id' => $this->customer2->id,
            'category_id' => $this->category->id,
            'subject' => 'Waiting on info',
            'description' => 'Waiting for reply',
            'priority' => Priority::Low,
            'status' => TicketStatus::WaitingForCustomer,
            'assigned_agent_id' => $this->agent1->id,
            'sla_due_at' => $now->copy()->addHours(5),
            'sla_warning_at' => $now->copy()->addHours(3),
        ]);

        // 5. Resolved Ticket
        Ticket::create([
            'ticket_number' => 'TKT-2026-000005',
            'customer_id' => $this->customer1->id,
            'category_id' => $this->category->id,
            'subject' => 'Resolved Issue',
            'description' => 'Fixed yesterday',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Resolved,
            'assigned_agent_id' => $this->agent1->id,
            'sla_due_at' => $now->copy()->addHours(4),
            'sla_warning_at' => $now->copy()->addHours(2),
        ]);

        // 6. Closed Ticket
        Ticket::create([
            'ticket_number' => 'TKT-2026-000006',
            'customer_id' => $this->customer2->id,
            'category_id' => $this->category->id,
            'subject' => 'Closed Issue',
            'description' => 'Confirmed done',
            'priority' => Priority::Low,
            'status' => TicketStatus::Closed,
            'assigned_agent_id' => null,
            'sla_due_at' => $now->copy()->addHours(8),
            'sla_warning_at' => $now->copy()->addHours(4),
        ]);

        $service = app(DashboardService::class);
        $data = $service->getAdminDashboardData();

        $stats = $data['stats'];
        $this->assertEquals(6, $stats->total);
        $this->assertEquals(4, $stats->open_total);
        $this->assertEquals(1, $stats->unassigned);
        $this->assertEquals(1, $stats->critical_open);
        $this->assertEquals(1, $stats->breached);
        $this->assertEquals(1, $stats->approaching);
        $this->assertEquals(2, $stats->resolved_total);

        // Priority breakdown
        $this->assertEquals(1, $data['priorityBreakdown']['critical'] ?? 0);
        $this->assertEquals(1, $data['priorityBreakdown']['high'] ?? 0);
        $this->assertEquals(1, $data['priorityBreakdown']['medium'] ?? 0);
        $this->assertEquals(1, $data['priorityBreakdown']['low'] ?? 0);

        // Agent workloads
        $agent1Workload = $data['agentsWorkload']->firstWhere('id', $this->agent1->id);
        $this->assertNotNull($agent1Workload);
        $this->assertEquals(2, $agent1Workload->tickets_count); // Breached + WaitingForCustomer

        $agent2Workload = $data['agentsWorkload']->firstWhere('id', $this->agent2->id);
        $this->assertNotNull($agent2Workload);
        $this->assertEquals(1, $agent2Workload->tickets_count); // Approaching
    }

    public function test_admin_dashboard_view_renders_successfully(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('System Command Center');
        $response->assertSee('Active Open');
        $response->assertSee('Unassigned');
        $response->assertSee('SLA Breached');
    }

    public function test_customer_dashboard_only_counts_own_tickets(): void
    {
        // Ticket for customer 1
        Ticket::create([
            'ticket_number' => 'TKT-2026-000010',
            'customer_id' => $this->customer1->id,
            'category_id' => $this->category->id,
            'subject' => 'Customer 1 Ticket',
            'description' => 'My issue',
            'priority' => Priority::Medium,
            'status' => TicketStatus::Open,
        ]);

        // Ticket for customer 2
        Ticket::create([
            'ticket_number' => 'TKT-2026-000011',
            'customer_id' => $this->customer2->id,
            'category_id' => $this->category->id,
            'subject' => 'Customer 2 Ticket',
            'description' => 'Other issue',
            'priority' => Priority::High,
            'status' => TicketStatus::Open,
        ]);

        $this->actingAs($this->customer1, 'customer');

        $response = $this->get(route('customer.dashboard'));

        $response->assertOk();
        $response->assertSee('Customer 1 Ticket');
        $response->assertDontSee('Customer 2 Ticket');
    }

    public function test_agent_dashboard_displays_workload_and_queues(): void
    {
        Ticket::create([
            'ticket_number' => 'TKT-2026-000020',
            'customer_id' => $this->customer1->id,
            'category_id' => $this->category->id,
            'subject' => 'Agent 1 Assigned Task',
            'description' => 'Work item',
            'priority' => Priority::High,
            'status' => TicketStatus::InProgress,
            'assigned_agent_id' => $this->agent1->id,
        ]);

        $this->actingAs($this->agent1, 'agent');

        $response = $this->get(route('agent.dashboard'));

        $response->assertOk();
        $response->assertSee('Agent 1 Assigned Task');
    }
}
