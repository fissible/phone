<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_numbers', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->default('global');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->string('phone_number');
            $table->string('friendly_name')->nullable();
            $table->string('provider_account_sid')->nullable();
            $table->string('provider_number_sid')->nullable();
            $table->string('messaging_service_sid')->nullable();
            $table->json('capabilities')->nullable();
            $table->boolean('voice_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('mms_enabled')->default(true);
            $table->string('routing_mode')->default('forward');
            $table->string('forward_to')->nullable();
            $table->json('business_hours')->nullable();
            $table->text('voicemail_greeting')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'phone_number'], 'phone_numbers_scope_number_unique');
            $table->index('provider_number_sid', 'phone_numbers_provider_number_sid_index');
            $table->index('status', 'phone_numbers_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
