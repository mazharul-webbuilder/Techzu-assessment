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
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();

            // Polymorphic author: can be customer, agent, or admin
            $table->string('author_type', 20);
            $table->unsignedBigInteger('author_id');

            $table->text('body');

            // Internal notes: true = internal agent/admin communication, never shown to customer
            $table->boolean('is_internal')->default(false);

            $table->timestamps();

            // Composite indexes for chronological conversation view and author lookups
            $table->index(['ticket_id', 'created_at'], 'idx_ticket_created');
            $table->index(['author_type', 'author_id'], 'idx_author');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
    }
};
