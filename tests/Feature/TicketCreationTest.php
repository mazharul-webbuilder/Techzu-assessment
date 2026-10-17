<?php

namespace Tests\Feature;

use App\Enums\ActivityEvent;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketCreationTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'tester@example.com',
            'password' => Hash::make('password'),
            'phone' => '+8801700000000',
        ]);

        $this->category = Category::create([
            'name' => 'General Inquiries',
            'is_active' => true,
        ]);
    }

    /**
     * Test a customer can view the ticket creation form.
     */
    public function test_customer_can_view_ticket_create_form(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->get(route('customer.tickets.create'));

        $response->assertStatus(200);
        $response->assertSee('Create Support Ticket');
        $response->assertSee('General Inquiries');
    }

    /**
     * Test a customer can create a ticket with valid data.
     * Verifies:
     * - Ticket is stored in database with status 'open'
     * - Sequence number adheres to TKT-YYYY-NNNNNN
     * - SLA deadlines are set
     * - An activity row is written for the 'created' event
     */
    public function test_customer_can_create_ticket_with_activity_log(): void
    {
        $payload = [
            'subject' => 'Cannot reset billing password',
            'description' => 'The reset link returns an expired token immediately.',
            'category_id' => $this->category->id,
            'priority' => Priority::High->value,
        ];

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.store'), $payload);

        $ticket = Ticket::first();

        $this->assertNotNull($ticket);
        $this->assertSame('Cannot reset billing password', $ticket->subject);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame(Priority::High, $ticket->priority);
        $this->assertMatchesRegularExpression('/^TKT-\d{4}-\d{6}$/', $ticket->ticket_number);
        $this->assertNotNull($ticket->sla_due_at);
        $this->assertNotNull($ticket->sla_warning_at);

        // Verify Activity Logger recorded the creation event
        $this->assertDatabaseHas('ticket_activities', [
            'ticket_id' => $ticket->id,
            'actor_type' => 'customer',
            'actor_id' => $this->customer->id,
            'event' => ActivityEvent::Created->value,
        ]);

        // Redirects to show page
        $response->assertRedirect(route('customer.tickets.show', $ticket));
    }

    /**
     * Test ticket creation is rejected with invalid input.
     */
    public function test_ticket_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.store'), [
                'subject' => '',
                'description' => '',
                'category_id' => 9999, // Non-existent
                'priority' => 'invalid-priority',
            ]);

        $response->assertSessionHasErrors(['subject', 'description', 'category_id', 'priority']);
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_activities', 0);
    }

    /**
     * Test ticket creation is rejected for inactive categories.
     */
    public function test_ticket_creation_rejects_inactive_category(): void
    {
        $inactiveCategory = Category::create([
            'name' => 'Deprecated Category',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.store'), [
                'subject' => 'Testing inactive category',
                'description' => 'Should fail validation',
                'category_id' => $inactiveCategory->id,
                'priority' => Priority::Medium->value,
            ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('tickets', 0);
    }

    /**
     * Test a customer can create a ticket with valid attachments.
     */
    public function test_customer_can_create_ticket_with_attachments(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $file1 = \Illuminate\Http\UploadedFile::fake()->create('screenshot.png', 500, 'image/png');
        $file2 = \Illuminate\Http\UploadedFile::fake()->create('error_log.txt', 200, 'text/plain');

        $payload = [
            'subject' => 'Issue with payment gateway',
            'description' => 'Attached screenshots showing error 500 on checkout.',
            'category_id' => $this->category->id,
            'priority' => Priority::Medium->value,
            'attachments' => [$file1, $file2],
        ];

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.store'), $payload);

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);
        $this->assertCount(2, $ticket->attachments);

        $attachment1 = $ticket->attachments->firstWhere('original_name', 'screenshot.png');
        $this->assertNotNull($attachment1);
        $this->assertSame('local', $attachment1->disk);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($attachment1->path);

        $response->assertRedirect(route('customer.tickets.show', $ticket));

        // Test downloading the attachment
        $downloadResponse = $this->actingAs($this->customer, 'customer')
            ->get(route('attachments.download', $attachment1));
        $downloadResponse->assertOk();

        // Another customer should be forbidden (403) from downloading this attachment
        $otherCustomer = Customer::create([
            'name' => 'Other Customer',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($otherCustomer, 'customer')
            ->get(route('attachments.download', $attachment1))
            ->assertStatus(403);
    }

    /**
     * Test attachment upload rejects disallowed file types.
     */
    public function test_attachment_upload_rejects_disallowed_file_types(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $disallowedFile = \Illuminate\Http\UploadedFile::fake()->create('malware.exe', 500);

        $payload = [
            'subject' => 'Malware test',
            'description' => 'Should fail mime validation',
            'category_id' => $this->category->id,
            'priority' => Priority::Low->value,
            'attachments' => [$disallowedFile],
        ];

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('customer.tickets.store'), $payload);

        $response->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('attachments', 0);
    }
}
