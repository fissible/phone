<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_voicemails', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->default('global');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->foreignId('phone_call_id')->nullable()->constrained('phone_calls')->nullOnDelete();
            $table->foreignId('phone_recording_id')->nullable()->constrained('phone_recordings')->nullOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('phone_numbers')->nullOnDelete();
            $table->foreignId('webhook_receipt_id')->nullable()->constrained('phone_webhook_receipts')->nullOnDelete();
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->string('status')->default('received');
            $table->text('recording_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('transcription_text')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('listened_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('phone_recording_id', 'phone_voicemails_recording_unique');
            $table->index(['phone_call_id', 'created_at'], 'phone_voicemails_call_created_index');
            $table->index(['phone_number_id', 'status'], 'phone_voicemails_number_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_voicemails');
    }
};
