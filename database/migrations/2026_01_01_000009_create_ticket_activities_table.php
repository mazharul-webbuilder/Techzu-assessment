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
        // Append-only audit trail. Records are never updated or soft-deleted.
        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();

            // Nullable polymorphic actor (null indicates system/scheduler action)
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('event', 50); // created, assigned, reassigned, status_changed, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description', 500)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['ticket_id', 'created_at'], 'idx_ticket_activities_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_activities');
    }
};
