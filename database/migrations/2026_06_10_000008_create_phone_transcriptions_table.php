<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_transcriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->default('global');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->foreignId('phone_call_id')->nullable()->constrained('phone_calls')->nullOnDelete();
            $table->foreignId('phone_recording_id')->nullable()->constrained('phone_recordings')->nullOnDelete();
            $table->foreignId('phone_voicemail_id')->nullable()->constrained('phone_voicemails')->nullOnDelete();
            $table->foreignId('phone_number_id')->nullable()->constrained('phone_numbers')->nullOnDelete();
            $table->foreignId('webhook_receipt_id')->nullable()->constrained('phone_webhook_receipts')->nullOnDelete();
            $table->string('provider_transcription_sid')->nullable();
            $table->string('provider_recording_sid')->nullable();
            $table->string('provider_call_sid')->nullable();
            $table->string('provider_account_sid')->nullable();
            $table->string('purpose')->nullable();
            $table->string('status');
            $table->unsignedInteger('status_rank')->default(0);
            $table->longText('transcription_text')->nullable();
            $table->text('transcription_url')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_transcription_sid'], 'phone_transcriptions_provider_sid_unique');
            $table->index(['phone_recording_id', 'created_at'], 'phone_transcriptions_recording_created_index');
            $table->index(['phone_voicemail_id', 'created_at'], 'phone_transcriptions_voicemail_created_index');
            $table->index(['purpose', 'status'], 'phone_transcriptions_purpose_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_transcriptions');
    }
};
