<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_threads', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->default('global');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->foreignId('phone_number_id')->constrained('phone_numbers')->cascadeOnDelete();
            $table->string('local_number');
            $table->string('remote_number');
            $table->string('remote_display_name')->nullable();
            $table->string('contact_type')->nullable();
            $table->string('contact_id')->nullable();
            $table->string('assigned_to_type')->nullable();
            $table->string('assigned_to_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_message_at')->nullable();
            $table->timestamp('last_outbound_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'phone_number_id', 'remote_number'], 'phone_threads_scope_number_remote_unique');
            $table->index('last_message_at', 'phone_threads_last_message_at_index');
            $table->index(['contact_type', 'contact_id'], 'phone_threads_contact_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_threads');
    }
};
