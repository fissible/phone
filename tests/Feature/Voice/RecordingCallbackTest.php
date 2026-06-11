<?php

declare(strict_types=1);

use Fissible\Phone\Events\RecordingStatusUpdated;
use Fissible\Phone\Events\VoicemailReceived;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneRecording;
use Fissible\Phone\Models\PhoneVoicemail;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\Support\RecordingStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    Carbon::setTestNow(Carbon::parse('2026-06-10 16:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('stores completed voicemail recordings and creates a voicemail once', function (): void {
    Event::fake([
        RecordingStatusUpdated::class,
        VoicemailReceived::class,
    ]);

    $call = recordingCallbackCall();
    $payload = recordingCallbackPayload();

    $this->post('/phone/twilio/voice/recording?call_id='.$call->id.'&purpose=voicemail', $payload)->assertNoContent();
    $this->post('/phone/twilio/voice/recording?call_id='.$call->id.'&purpose=voicemail', $payload)->assertNoContent();

    $recording = PhoneRecording::query()->sole();
    $voicemail = PhoneVoicemail::query()->sole();
    $receipt = WebhookReceipt::query()->where('event_type', 'voice.recording')->sole();

    expect($recording->phone_call_id)->toBe($call->id)
        ->and($recording->phone_number_id)->toBe($call->phone_number_id)
        ->and($recording->webhook_receipt_id)->toBe($receipt->id)
        ->and($recording->scope_key)->toBe('tenant:acme')
        ->and($recording->provider_recording_sid)->toBe('RE'.str_repeat('1', 32))
        ->and($recording->provider_call_sid)->toBe('CA'.str_repeat('7', 32))
        ->and($recording->purpose)->toBe('voicemail')
        ->and($recording->status)->toBe(RecordingStatus::COMPLETED)
        ->and($recording->status_rank)->toBe(RecordingStatus::rank(RecordingStatus::COMPLETED))
        ->and($recording->recording_url)->toBe('https://api.twilio.com/recordings/RE111')
        ->and($recording->duration_seconds)->toBe(37)
        ->and($recording->channels)->toBe(1)
        ->and($recording->source)->toBe('RecordVerb')
        ->and($recording->track)->toBe('inbound')
        ->and($recording->metadata['twilio_recording_callback']['purpose'])->toBe('voicemail')
        ->and($voicemail->phone_call_id)->toBe($call->id)
        ->and($voicemail->phone_recording_id)->toBe($recording->id)
        ->and($voicemail->from_number)->toBe('+16615551212')
        ->and($voicemail->to_number)->toBe('+16615550100')
        ->and($voicemail->recording_url)->toBe('https://api.twilio.com/recordings/RE111')
        ->and($voicemail->duration_seconds)->toBe(37)
        ->and($voicemail->received_at?->toDateTimeString())->toBe('2026-06-10 16:00:00')
        ->and($receipt->provider_sid)->toBe('RE'.str_repeat('1', 32))
        ->and($receipt->processing_status)->toBe('processed');

    Event::assertDispatchedTimes(RecordingStatusUpdated::class, 1);
    Event::assertDispatchedTimes(VoicemailReceived::class, 1);
});

it('stores non-voicemail recordings without creating voicemail records', function (): void {
    Event::fake([
        RecordingStatusUpdated::class,
        VoicemailReceived::class,
    ]);

    $call = recordingCallbackCall();

    $this->post('/phone/twilio/voice/recording?call_id='.$call->id.'&purpose=quality', recordingCallbackPayload([
        'RecordingSid' => 'RE'.str_repeat('2', 32),
    ]))->assertNoContent();

    $recording = PhoneRecording::query()->sole();

    expect($recording->purpose)->toBe('quality')
        ->and(PhoneVoicemail::query()->count())->toBe(0);

    Event::assertDispatchedTimes(RecordingStatusUpdated::class, 1);
    Event::assertNotDispatched(VoicemailReceived::class);
});

function recordingCallbackCall(): PhoneCall
{
    $number = PhoneNumber::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'status' => 'active',
    ]);

    return PhoneCall::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_number_id' => $number->id,
        'provider_call_sid' => 'CA'.str_repeat('7', 32),
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'direction' => 'inbound',
        'from_number' => '+16615551212',
        'to_number' => '+16615550100',
        'status' => CallStatus::COMPLETED,
        'status_rank' => CallStatus::rank(CallStatus::COMPLETED),
        'started_at' => Carbon::parse('2026-06-10 15:58:00'),
        'ended_at' => Carbon::parse('2026-06-10 16:00:00'),
    ]);
}

/** @param array<string, string> $overrides */
function recordingCallbackPayload(array $overrides = []): array
{
    return array_merge([
        'RecordingSid' => 'RE'.str_repeat('1', 32),
        'CallSid' => 'CA'.str_repeat('7', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'RecordingStatus' => 'completed',
        'RecordingUrl' => 'https://api.twilio.com/recordings/RE111',
        'RecordingDuration' => '37',
        'RecordingChannels' => '1',
        'RecordingSource' => 'RecordVerb',
        'RecordingTrack' => 'inbound',
    ], $overrides);
}
