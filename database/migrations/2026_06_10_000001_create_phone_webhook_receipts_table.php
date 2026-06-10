<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('twilio');
            $table->string('event_type');
            $table->string('provider_sid')->nullable();
            $table->string('request_method', 16);
            $table->text('request_url');
            $table->string('request_hash', 64);
            $table->string('source_ip')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->string('processing_status')->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('replay_count')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'event_type', 'request_hash'], 'phone_receipts_provider_event_hash_unique');
            $table->index('provider_sid', 'phone_receipts_provider_sid_index');
            $table->index('processing_status', 'phone_receipts_status_index');
            $table->index('event_type', 'phone_receipts_event_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_webhook_receipts');
    }
};
