<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Jobs\ImportTicketsJob;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketImportTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::create([
            'name' => 'System Admin',
            'email' => 'admin@test.test',
            'password' => bcrypt('password'),
        ]);
        Storage::fake('local');
    }

    public function test_admin_can_view_import_pages_and_submit_csv(): void
    {
        $this->actingAs($this->admin, 'admin');

        $response = $this->get(route('admin.imports.index'));
        $response->assertOk();
        $response->assertSee('Ticket Batch Imports');

        $response = $this->get(route('admin.imports.create'));
        $response->assertOk();
        $response->assertSee('Import Support Tickets');

        $csvContent = implode("\n", [
            'external_id,customer_email,customer_name,subject,description,priority,category,status,created_at',
            'EXT-10,test@example.com,Test User,Subject 1,Description 1,high,Billing,open,2026-03-01 10:00:00',
        ]);

        $file = UploadedFile::fake()->createWithContent('tickets.csv', $csvContent);

        $response = $this->post(route('admin.imports.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('imports', [
            'admin_id' => $this->admin->id,
            'status' => 'completed', // sync queue runs immediately
        ]);
    }

    public function test_import_job_processes_valid_rows_skips_duplicates_and_records_errors(): void
    {
        $csvContent = implode("\n", [
            'external_id,customer_email,customer_name,subject,description,priority,category,status,created_at',
            'EXT-1,alice@example.com,Alice,First Ticket,Valid description,high,Billing,open,2026-03-01 09:00:00',
            'EXT-1,alice@example.com,Alice,Duplicate Ticket,Should be skipped,high,Billing,open,2026-03-01 09:05:00',
            'EXT-2,not-an-email,Bob,Bad Email Ticket,Invalid email row,medium,Support,open,2026-03-01 09:10:00',
            'EXT-3,charlie@example.com,Charlie,Second Ticket,Valid description,critical,Hardware,in_progress,2026-03-01 09:15:00',
        ]);

        $storedPath = Storage::disk('local')->putFileAs('imports', UploadedFile::fake()->createWithContent('import.csv', $csvContent), 'batch.csv');

        $import = Import::create([
            'admin_id' => $this->admin->id,
            'file_path' => $storedPath,
            'status' => 'pending',
        ]);

        ImportTicketsJob::dispatchSync($import);

        $import->refresh();

        $this->assertEquals('completed', $import->status);
        $this->assertEquals(4, $import->total_rows);
        $this->assertEquals(2, $import->processed_rows);
        $this->assertEquals(1, $import->skipped_duplicates);
        $this->assertEquals(1, $import->failed_rows);
        $this->assertNotNull($import->error_report_path);
        $this->assertTrue(Storage::disk('local')->exists($import->error_report_path));

        // Verify created tickets
        $ticket1 = Ticket::where('external_id', 'EXT-1')->first();
        $this->assertNotNull($ticket1);
        $this->assertEquals('First Ticket', $ticket1->subject);
        $this->assertEquals(Priority::High, $ticket1->priority);
        $this->assertEquals(TicketStatus::Open, $ticket1->status);
        $this->assertEquals('alice@example.com', $ticket1->customer->email);
        $this->assertEquals('Billing', $ticket1->category->name);

        $ticket3 = Ticket::where('external_id', 'EXT-3')->first();
        $this->assertNotNull($ticket3);
        $this->assertEquals('Second Ticket', $ticket3->subject);
        $this->assertEquals(Priority::Critical, $ticket3->priority);
        $this->assertEquals(TicketStatus::InProgress, $ticket3->status);

        // Verify error report content
        $errorContent = Storage::disk('local')->get($import->error_report_path);
        $this->assertStringContainsString('row_number,error_reason,raw_data', $errorContent);
        $this->assertStringContainsString('4', $errorContent);
        $this->assertStringContainsString('The customer email field must be a valid email address', $errorContent);
    }

    public function test_re_importing_same_file_is_idempotent_and_skips_all_duplicates(): void
    {
        $csvContent = implode("\n", [
            'external_id,customer_email,customer_name,subject,description,priority,category,status,created_at',
            'REIMP-1,user1@example.com,User One,First Subject,Desc 1,low,Billing,open,2026-03-01 10:00:00',
            'REIMP-2,user2@example.com,User Two,Second Subject,Desc 2,medium,Technical,open,2026-03-01 11:00:00',
        ]);

        $path1 = Storage::disk('local')->putFileAs('imports', UploadedFile::fake()->createWithContent('first.csv', $csvContent), 'first.csv');
        $import1 = Import::create([
            'admin_id' => $this->admin->id,
            'file_path' => $path1,
            'status' => 'pending',
        ]);

        ImportTicketsJob::dispatchSync($import1);
        $this->assertEquals(2, $import1->fresh()->processed_rows);
        $this->assertEquals(2, Ticket::whereIn('external_id', ['REIMP-1', 'REIMP-2'])->count());

        // Re-import exact same file in a second batch
        $path2 = Storage::disk('local')->putFileAs('imports', UploadedFile::fake()->createWithContent('second.csv', $csvContent), 'second.csv');
        $import2 = Import::create([
            'admin_id' => $this->admin->id,
            'file_path' => $path2,
            'status' => 'pending',
        ]);

        ImportTicketsJob::dispatchSync($import2);

        $import2->refresh();
        $this->assertEquals(0, $import2->processed_rows);
        $this->assertEquals(2, $import2->skipped_duplicates);
        $this->assertEquals(0, $import2->failed_rows);

        // Ticket count in DB remains exactly 2
        $this->assertEquals(2, Ticket::whereIn('external_id', ['REIMP-1', 'REIMP-2'])->count());
    }

    public function test_non_admin_cannot_access_import_endpoints(): void
    {
        $customer = Customer::create([
            'name' => 'Customer One',
            'email' => 'cust@example.com',
            'password' => bcrypt('password'),
        ]);
        $agent = Agent::create([
            'name' => 'Agent One',
            'email' => 'agent@example.com',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        // Customer cannot access admin routes (redirects to admin.login)
        $this->actingAs($customer, 'customer');
        $this->get(route('admin.imports.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.imports.create'))->assertRedirect(route('admin.login'));

        // Agent cannot access admin routes (redirects to admin.login)
        auth('customer')->logout();
        $this->actingAs($agent, 'agent');
        $this->get(route('admin.imports.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.imports.create'))->assertRedirect(route('admin.login'));

        // Guest redirected to admin login
        auth('agent')->logout();
        $this->get(route('admin.imports.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_download_error_report(): void
    {
        $this->actingAs($this->admin, 'admin');

        $import = Import::create([
            'admin_id' => $this->admin->id,
            'file_path' => 'imports/dummy.csv',
            'status' => 'completed',
            'total_rows' => 1,
            'failed_rows' => 1,
            'error_report_path' => 'imports/test_errors.csv',
        ]);

        Storage::disk('local')->put('imports/test_errors.csv', "row_number,error_reason,raw_data\n2,Invalid priority,raw");

        $response = $this->get(route('admin.imports.errors', $import));

        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
    }
}
