<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\Agent;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected Customer $customerAlice;
    protected Customer $customerBob;
    protected Agent $agent;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->category = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
            'is_active' => true,
        ]);

        $this->customerAlice = Customer::create([
            'name' => 'Alice Customer',
            'email' => 'alice@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->customerBob = Customer::create([
            'name' => 'Bob Customer',
            'email' => 'bob@test.test',
            'password' => bcrypt('password'),
        ]);

        $this->agent = Agent::create([
            'name' => 'Support Agent',
            'email' => 'agent@test.test',
            'password' => bcrypt('password'),
            'is_available' => true,
        ]);

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'email' => 'admin@test.test',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_customer_can_upload_valid_attachment_on_ticket_creation(): void
    {
        $file = UploadedFile::fake()->create('screenshot.png', 500, 'image/png');

        $response = $this->actingAs($this->customerAlice, 'customer')
            ->post(route('customer.tickets.store'), [
                'subject' => 'UI Bug with screenshot',
                'description' => 'See attached file',
                'category_id' => $this->category->id,
                'priority' => Priority::Medium->value,
                'attachments' => [$file],
            ]);

        $response->assertSessionHasNoErrors();

        $ticket = Ticket::where('customer_id', $this->customerAlice->id)->first();
        $this->assertNotNull($ticket);

        $attachment = Attachment::where('ticket_id', $ticket->id)->first();
        $this->assertNotNull($attachment);
        $this->assertSame('screenshot.png', $attachment->original_name);
        $this->assertSame('local', $attachment->disk);
        $this->assertTrue(Storage::disk('local')->exists($attachment->path));
    }

    public function test_attachment_upload_rejects_oversized_files(): void
    {
        // 6000 KB exceeds 5120 KB (5MB) limit
        $oversizedFile = UploadedFile::fake()->create('huge_log.txt', 6000, 'text/plain');

        $response = $this->actingAs($this->customerAlice, 'customer')
            ->post(route('customer.tickets.store'), [
                'subject' => 'Crash Log',
                'description' => 'Log file attached',
                'category_id' => $this->category->id,
                'priority' => Priority::Medium->value,
                'attachments' => [$oversizedFile],
            ]);

        $response->assertSessionHasErrors(['attachments.0']);
    }

    public function test_attachment_upload_rejects_disallowed_mime_types(): void
    {
        $executableFile = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->customerAlice, 'customer')
            ->post(route('customer.tickets.store'), [
                'subject' => 'Patch',
                'description' => 'Executable',
                'category_id' => $this->category->id,
                'priority' => Priority::Medium->value,
                'attachments' => [$executableFile],
            ]);

        $response->assertSessionHasErrors(['attachments.0']);
    }

    public function test_another_customer_cannot_download_attachment(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-ATTACH-001',
            'customer_id' => $this->customerAlice->id,
            'category_id' => $this->category->id,
            'subject' => 'Alice Private Document',
            'description' => 'Confidential',
            'status' => TicketStatus::Open,
            'priority' => Priority::Medium,
        ]);

        Storage::disk('local')->put("tickets/{$ticket->id}/secret.pdf", 'Alice secret file content');

        $attachment = Attachment::create([
            'ticket_id' => $ticket->id,
            'uploaded_by_type' => $this->customerAlice->getMorphClass(),
            'uploaded_by_id' => $this->customerAlice->id,
            'disk' => 'local',
            'path' => "tickets/{$ticket->id}/secret.pdf",
            'original_name' => 'secret.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
        ]);

        // Customer Bob attempts to download Alice's attachment
        $response = $this->actingAs($this->customerBob, 'customer')
            ->get(route('attachments.download', $attachment));

        $response->assertStatus(403);
    }

    public function test_customer_cannot_download_attachment_from_internal_note(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-ATTACH-002',
            'customer_id' => $this->customerAlice->id,
            'category_id' => $this->category->id,
            'subject' => 'Account Audit',
            'description' => 'Checking records',
            'status' => TicketStatus::InProgress,
            'priority' => Priority::High,
        ]);

        $internalReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'author_type' => $this->agent->getMorphClass(),
            'author_id' => $this->agent->id,
            'body' => 'Confidential agent assessment report.',
            'is_internal' => true,
        ]);

        Storage::disk('local')->put("tickets/{$ticket->id}/internal_audit.pdf", 'Confidential staff notes');

        $internalAttachment = Attachment::create([
            'ticket_id' => $ticket->id,
            'reply_id' => $internalReply->id,
            'uploaded_by_type' => $this->agent->getMorphClass(),
            'uploaded_by_id' => $this->agent->id,
            'disk' => 'local',
            'path' => "tickets/{$ticket->id}/internal_audit.pdf",
            'original_name' => 'internal_audit.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
        ]);

        // Customer Alice (the ticket owner) attempts to download file from internal note
        $customerResponse = $this->actingAs($this->customerAlice, 'customer')
            ->get(route('attachments.download', $internalAttachment));

        // Must be forbidden (403)
        $customerResponse->assertStatus(403);
    }

    public function test_staff_agent_and_admin_can_download_attachment_from_internal_note(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TCK-ATTACH-003',
            'customer_id' => $this->customerAlice->id,
            'category_id' => $this->category->id,
            'assigned_agent_id' => $this->agent->id,
            'subject' => 'Account Audit Staff View',
            'description' => 'Checking records',
            'status' => TicketStatus::InProgress,
            'priority' => Priority::High,
        ]);

        $internalReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'author_type' => $this->agent->getMorphClass(),
            'author_id' => $this->agent->id,
            'body' => 'Confidential staff notes.',
            'is_internal' => true,
        ]);

        Storage::disk('local')->put("tickets/{$ticket->id}/staff_audit.pdf", 'Confidential staff file');

        $internalAttachment = Attachment::create([
            'ticket_id' => $ticket->id,
            'reply_id' => $internalReply->id,
            'uploaded_by_type' => $this->agent->getMorphClass(),
            'uploaded_by_id' => $this->agent->id,
            'disk' => 'local',
            'path' => "tickets/{$ticket->id}/staff_audit.pdf",
            'original_name' => 'staff_audit.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 2048,
        ]);

        // Staff agent CAN download the internal attachment
        $agentResponse = $this->actingAs($this->agent, 'agent')
            ->get(route('attachments.download', $internalAttachment));
        $agentResponse->assertStatus(200);

        // Admin CAN download the internal attachment
        auth('agent')->logout();
        $adminResponse = $this->actingAs($this->admin, 'admin')
            ->get(route('attachments.download', $internalAttachment));
        $adminResponse->assertStatus(200);
    }

    public function test_attachments_are_stored_on_private_disk_never_public(): void
    {
        $file = UploadedFile::fake()->create('invoice.pdf', 300, 'application/pdf');

        $this->actingAs($this->customerAlice, 'customer')
            ->post(route('customer.tickets.store'), [
                'subject' => 'Invoice dispute',
                'description' => 'See invoice',
                'category_id' => $this->category->id,
                'priority' => Priority::Low->value,
                'attachments' => [$file],
            ]);

        $attachment = Attachment::latest()->first();
        $this->assertSame('local', $attachment->disk);
        $this->assertFalse(Storage::disk('public')->exists($attachment->path));
        $this->assertTrue(Storage::disk('local')->exists($attachment->path));
    }
}
