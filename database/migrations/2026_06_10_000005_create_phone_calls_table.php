<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->default('global');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->foreignId('phone_number_id')->nullable()->constrained('phone_numbers')->nullOnDelete();
            $table->foreignId('webhook_receipt_id')->nullable()->constrained('phone_webhook_receipts')->nullOnDelete();
            $table->string('provider_call_sid')->nullable();
            $table->string('provider_parent_call_sid')->nullable();
            $table->string('provider_account_sid')->nullable();
            $table->unsignedInteger('provider_sequence_number')->nullable();
            $table->string('direction');
            $table->string('from_number');
            $table->string('to_number');
            $table->string('status');
            $table->unsignedInteger('status_rank')->default(0);
            $table->string('routing_mode')->nullable();
            $table->json('route_decision')->nullable();
            $table->string('answered_by')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_call_sid'], 'phone_calls_provider_sid_unique');
            $table->index(['phone_number_id', 'created_at'], 'phone_calls_number_created_index');
            $table->index('status', 'phone_calls_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_calls');
    }
};
