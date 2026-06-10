<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->default('global');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->foreignId('phone_thread_id')->nullable()->constrained('phone_threads')->nullOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('phone_numbers')->nullOnDelete();
            $table->foreignId('webhook_receipt_id')->nullable()->constrained('phone_webhook_receipts')->nullOnDelete();
            $table->string('provider_message_sid')->nullable();
            $table->string('provider_account_sid')->nullable();
            $table->string('direction');
            $table->string('from_number');
            $table->string('to_number');
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->unsignedInteger('num_segments')->nullable();
            $table->string('status');
            $table->unsignedInteger('status_rank')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_message_sid'], 'phone_messages_provider_sid_unique');
            $table->index(['phone_thread_id', 'created_at'], 'phone_messages_thread_created_index');
            $table->index('phone_number_id', 'phone_messages_phone_number_id_index');
            $table->index('status', 'phone_messages_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_messages');
    }
};
