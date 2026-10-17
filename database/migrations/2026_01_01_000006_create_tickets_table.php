<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            // Predefined format TKT-YYYY-NNNNNN (e.g., TKT-2026-000001)
            $table->string('ticket_number', 20)->unique();
            // Optional external identifier used during CSV import for deduplication
            $table->string('external_id', 100)->nullable()->unique();
            $table->string('subject', 255);
            $table->text('description');
            $table->string('priority', 20); // low, medium, high, critical
            $table->string('status', 30);   // open, in_progress, waiting_for_customer, resolved, closed

            // Foreign keys
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();

            // Stored SLA calculations (precomputed in business hours to enable indexed range scans)
            $table->dateTime('sla_due_at')->nullable();
            $table->dateTime('sla_warning_at')->nullable();
            $table->dateTime('sla_warning_notified_at')->nullable();
            $table->dateTime('sla_breach_notified_at')->nullable();

            // Lifecycle milestone timestamps
            $table->dateTime('escalated_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->timestamps();

            // Composite indexes optimized for dashboard queries, filtering, and scheduled scans
            $table->index(['status', 'priority'], 'idx_status_priority');
            $table->index(['assigned_agent_id', 'status'], 'idx_agent_status');
            $table->index(['status', 'sla_due_at'], 'idx_status_due');
            $table->index(['status', 'sla_warning_at'], 'idx_status_warning');
            $table->index(['customer_id', 'status'], 'idx_customer_status');
            $table->index(['created_at'], 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
