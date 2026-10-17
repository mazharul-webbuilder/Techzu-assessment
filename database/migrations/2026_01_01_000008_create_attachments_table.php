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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            // NULL when attached directly to the main ticket; populated when attached to a specific reply
            $table->foreignId('reply_id')->nullable()->constrained('ticket_replies')->cascadeOnDelete();

            // Polymorphic uploader (Customer, Agent, or Admin)
            $table->string('uploaded_by_type', 20);
            $table->unsignedBigInteger('uploaded_by_id');

            // Secure file storage metadata
            $table->string('disk', 20)->default('local');
            $table->string('path', 500);          // Obfuscated stored path (UUID), never raw user filename
            $table->string('original_name', 255); // Original file name for user display
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');

            $table->timestamps();

            $table->index('ticket_id', 'idx_ticket');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
