<?php

declare(strict_types=1);

use Fissible\Phone\Events\TranscriptionStatusUpdated;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneRecording;
use Fissible\Phone\Models\PhoneTranscription;
use Fissible\Phone\Models\PhoneVoicemail;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\Support\RecordingStatus;
use Fissible\Phone\Support\TranscriptionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    Carbon::setTestNow(Carbon::parse('2026-06-10 17:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('stores completed voicemail transcriptions and enriches the voicemail', function (): void {
    Event::fake([TranscriptionStatusUpdated::class]);

    $recording = transcriptionCallbackRecording('voicemail');
    $voicemail = PhoneVoicemail::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_call_id' => $recording->phone_call_id,
        'phone_recording_id' => $recording->id,
        'phone_number_id' => $recording->phone_number_id,
        'from_number' => '+16615551212',
        'to_number' => '+16615550100',
        'status' => 'received',
        'recording_url' => $recording->recording_url,
        'duration_seconds' => 37,
        'received_at' => Carbon::parse('2026-06-10 16:59:00'),
    ]);

    $payload = transcriptionCallbackPayload();

    $this->post('/phone/twilio/voice/transcription?call_id='.$recording->phone_call_id.'&purpose=voicemail', $payload)->assertNoContent();
    $this->post('/phone/twilio/voice/transcription?call_id='.$recording->phone_call_id.'&purpose=voicemail', $payload)->assertNoContent();

    $transcription = PhoneTranscription::query()->sole();
    $receipt = WebhookReceipt::query()->where('event_type', 'voice.transcription')->sole();
    $voicemail->refresh();

    expect($transcription->phone_recording_id)->toBe($recording->id)
        ->and($transcription->phone_voicemail_id)->toBe($voicemail->id)
        ->and($transcription->phone_call_id)->toBe($recording->phone_call_id)
        ->and($transcription->scope_key)->toBe('tenant:acme')
        ->and($transcription->provider_transcription_sid)->toBe('TR'.str_repeat('1', 32))
        ->and($transcription->provider_recording_sid)->toBe('RE'.str_repeat('1', 32))
        ->and($transcription->purpose)->toBe('voicemail')
        ->and($transcription->status)->toBe(TranscriptionStatus::COMPLETED)
        ->and($transcription->status_rank)->toBe(TranscriptionStatus::rank(TranscriptionStatus::COMPLETED))
        ->and($transcription->transcription_text)->toBe('The customer asked for a callback.')
        ->and($transcription->transcription_url)->toBe('https://api.twilio.com/transcriptions/TR111')
        ->and($transcription->webhook_receipt_id)->toBe($receipt->id)
        ->and($voicemail->status)->toBe('transcribed')
        ->and($voicemail->transcription_text)->toBe('The customer asked for a callback.')
        ->and($voicemail->metadata['phone_transcription_id'])->toBe($transcription->id)
        ->and($receipt->provider_sid)->toBe('TR'.str_repeat('1', 32))
        ->and($receipt->processing_status)->toBe('processed');

    Event::assertDispatchedTimes(TranscriptionStatusUpdated::class, 1);
    Event::assertDispatched(TranscriptionStatusUpdated::class, function (TranscriptionStatusUpdated $event) use ($transcription, $voicemail, $receipt): bool {
        return $event->transcription->is($transcription)
            && $event->oldStatus === null
            && $event->newStatus === TranscriptionStatus::COMPLETED
            && $event->voicemail?->is($voicemail)
            && $event->webhookReceipt?->is($receipt);
    });
});

it('does not treat non-voicemail transcriptions as voicemail content', function (): void {
    Event::fake([TranscriptionStatusUpdated::class]);

    $recording = transcriptionCallbackRecording('quality');

    $this->post('/phone/twilio/voice/transcription?call_id='.$recording->phone_call_id.'&purpose=quality', transcriptionCallbackPayload([
        'TranscriptionSid' => 'TR'.str_repeat('2', 32),
    ]))->assertNoContent();

    $transcription = PhoneTranscription::query()->sole();

    expect($transcription->purpose)->toBe('quality')
        ->and($transcription->phone_voicemail_id)->toBeNull()
        ->and(PhoneVoicemail::query()->count())->toBe(0);

    Event::assertDispatchedTimes(TranscriptionStatusUpdated::class, 1);
});

function transcriptionCallbackRecording(string $purpose): PhoneRecording
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

    $call = PhoneCall::query()->create([
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
        'started_at' => Carbon::parse('2026-06-10 16:58:00'),
        'ended_at' => Carbon::parse('2026-06-10 17:00:00'),
    ]);

    return PhoneRecording::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_call_id' => $call->id,
        'phone_number_id' => $number->id,
        'provider_recording_sid' => 'RE'.str_repeat('1', 32),
        'provider_call_sid' => $call->provider_call_sid,
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'purpose' => $purpose,
        'status' => RecordingStatus::COMPLETED,
        'status_rank' => RecordingStatus::rank(RecordingStatus::COMPLETED),
        'recording_url' => 'https://api.twilio.com/recordings/RE111',
        'duration_seconds' => 37,
    ]);
}

/** @param array<string, string> $overrides */
function transcriptionCallbackPayload(array $overrides = []): array
{
    return array_merge([
        'TranscriptionSid' => 'TR'.str_repeat('1', 32),
        'RecordingSid' => 'RE'.str_repeat('1', 32),
        'CallSid' => 'CA'.str_repeat('7', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'TranscriptionStatus' => 'completed',
        'TranscriptionText' => 'The customer asked for a callback.',
        'TranscriptionUrl' => 'https://api.twilio.com/transcriptions/TR111',
        'RecordingUrl' => 'https://api.twilio.com/recordings/RE111',
    ], $overrides);
}
