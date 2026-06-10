<?php

declare(strict_types=1);

use Fissible\Phone\Http\Controllers\TwilioWebhookController;
use Illuminate\Support\Facades\Route;

$middleware = config('phone.webhooks.middleware', ['phone.twilio']);

if (is_string($middleware)) {
    $middleware = [$middleware];
}

Route::prefix(config('phone.route_prefix', 'phone'))
    ->middleware($middleware)
    ->name('phone.twilio.')
    ->group(function (): void {
        Route::post('twilio/sms/inbound', [TwilioWebhookController::class, 'inboundSms'])
            ->defaults('phone_event_type', 'sms.inbound')
            ->name('sms.inbound');

        Route::post('twilio/sms/status', [TwilioWebhookController::class, 'smsStatus'])
            ->defaults('phone_event_type', 'sms.status')
            ->name('sms.status');

        Route::post('twilio/voice/inbound', [TwilioWebhookController::class, 'inboundVoice'])
            ->defaults('phone_event_type', 'voice.inbound')
            ->name('voice.inbound');

        Route::post('twilio/voice/dial-status', [TwilioWebhookController::class, 'dialStatus'])
            ->defaults('phone_event_type', 'voice.dial_status')
            ->name('voice.dial-status');

        Route::post('twilio/voice/status', [TwilioWebhookController::class, 'voiceStatus'])
            ->defaults('phone_event_type', 'voice.status')
            ->name('voice.status');

        Route::post('twilio/voice/recording', [TwilioWebhookController::class, 'recording'])
            ->defaults('phone_event_type', 'voice.recording')
            ->name('voice.recording');

        Route::post('twilio/voice/transcription', [TwilioWebhookController::class, 'transcription'])
            ->defaults('phone_event_type', 'voice.transcription')
            ->name('voice.transcription');

        Route::post('twilio/ai/status', [TwilioWebhookController::class, 'aiStatus'])
            ->defaults('phone_event_type', 'ai.status')
            ->name('ai.status');
    });
