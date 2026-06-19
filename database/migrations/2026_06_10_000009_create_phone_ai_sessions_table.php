<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_ai_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key')->default('global');
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('provider')->default('twilio');
            $table->foreignId('phone_call_id')->nullable()->constrained('phone_calls')->nullOnDelete();
            $table->string('provider_session_sid')->nullable();
            $table->string('mode');
            $table->string('status');
            $table->text('websocket_url')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->longText('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->string('handoff_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('phone_call_id', 'phone_ai_sessions_call_index');
            $table->index('status', 'phone_ai_sessions_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_ai_sessions');
    }
};
