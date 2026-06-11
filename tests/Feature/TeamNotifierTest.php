<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\TeamNotifier;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneRecording;
use Fissible\Phone\Models\PhoneVoicemail;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\ValueObjects\TeamNotification;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    Carbon::setTestNow(Carbon::parse('2026-06-10 17:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('notifies teams about inbound sms after persistence', function (): void {
    $notifier = new class implements TeamNotifier
    {
        /** @var list<TeamNotification> */
        public array $notifications = [];

        public function notify(TeamNotification $notification): void
        {
            $this->notifications[] = $notification;
        }
    };

    app()->instance(TeamNotifier::class, $notifier);

    $this->post('/phone/twilio/sms/inbound', teamNotifierInboundSmsPayload())->assertNoContent();

    $message = PhoneMessage::query()->sole();
    $notification = $notifier->notifications[0] ?? null;

    expect($notifier->notifications)->toHaveCount(1)
        ->and($notification)->toBeInstanceOf(TeamNotification::class)
        ->and($notification->type)->toBe('sms.inbound')
        ->and($notification->channel)->toBe('sms')
        ->and($notification->direction)->toBe('inbound')
        ->and($notification->message?->is($message))->toBeTrue()
        ->and($notification->thread?->is($message->thread))->toBeTrue()
        ->and($notification->phoneNumber?->is($message->phoneNumber))->toBeTrue()
        ->and($notification->contact?->displayName)->toBe('+16615551212')
        ->and($notification->metadata['provider_message_sid'])->toBe('SM'.str_repeat('2', 32));
});

it('notifies teams about missed inbound calls once', function (): void {
    $notifier = new class implements TeamNotifier
    {
        /** @var list<TeamNotification> */
        public array $notifications = [];

        public function notify(TeamNotification $notification): void
        {
            $this->notifications[] = $notification;
        }
    };

    app()->instance(TeamNotifier::class, $notifier);

    $call = teamNotifierCall([
        'metadata' => [
            'contact' => [
                'display_name' => 'Sam Lead',
                'external_type' => 'lead',
                'external_id' => 'lead_123',
                'resolved' => true,
            ],
        ],
    ]);

    $this->post('/phone/twilio/voice/dial-status?call_id='.$call->id, teamNotifierDialStatusPayload([
        'DialCallStatus' => 'no-answer',
        'DialCallDuration' => '18',
        'DialBridged' => 'false',
    ]))->assertOk();

    $this->post('/phone/twilio/voice/dial-status?call_id='.$call->id, teamNotifierDialStatusPayload([
        'DialCallStatus' => 'no-answer',
        'DialCallDuration' => '18',
        'DialBridged' => 'false',
    ]))->assertOk();

    $call->refresh();
    $notification = $notifier->notifications[0] ?? null;

    expect($call->status)->toBe(CallStatus::NO_ANSWER)
        ->and($notifier->notifications)->toHaveCount(1)
        ->and($notification)->toBeInstanceOf(TeamNotification::class)
        ->and($notification->type)->toBe('voice.missed')
        ->and($notification->channel)->toBe('voice')
        ->and($notification->direction)->toBe('inbound')
        ->and($notification->call?->is($call))->toBeTrue()
        ->and($notification->phoneNumber?->id)->toBe($call->phone_number_id)
        ->and($notification->contact?->displayName)->toBe('Sam Lead')
        ->and($notification->contact?->externalType)->toBe('lead')
        ->and($notification->contact?->externalId)->toBe('lead_123')
        ->and($notification->metadata['provider_call_sid'])->toBe('CA'.str_repeat('7', 32))
        ->and($notification->metadata['provider_status'])->toBe('no-answer')
        ->and($notification->metadata['source'])->toBe('twilio_dial_status_callback');
});

it('notifies teams about new voicemails once', function (): void {
    $notifier = new class implements TeamNotifier
    {
        /** @var list<TeamNotification> */
        public array $notifications = [];

        public function notify(TeamNotification $notification): void
        {
            $this->notifications[] = $notification;
        }
    };

    app()->instance(TeamNotifier::class, $notifier);

    $call = teamNotifierCall([
        'status' => CallStatus::COMPLETED,
        'status_rank' => CallStatus::rank(CallStatus::COMPLETED),
        'ended_at' => Carbon::parse('2026-06-10 16:59:30'),
    ]);
    $payload = teamNotifierRecordingPayload();

    $this->post('/phone/twilio/voice/recording?call_id='.$call->id.'&purpose=voicemail', $payload)->assertNoContent();
    $this->post('/phone/twilio/voice/recording?call_id='.$call->id.'&purpose=voicemail', $payload)->assertNoContent();

    $recording = PhoneRecording::query()->sole();
    $voicemail = PhoneVoicemail::query()->sole();
    $notification = $notifier->notifications[0] ?? null;

    expect($notifier->notifications)->toHaveCount(1)
        ->and($notification)->toBeInstanceOf(TeamNotification::class)
        ->and($notification->type)->toBe('voicemail.received')
        ->and($notification->channel)->toBe('voice')
        ->and($notification->direction)->toBe('inbound')
        ->and($notification->call?->is($call))->toBeTrue()
        ->and($notification->recording?->is($recording))->toBeTrue()
        ->and($notification->voicemail?->is($voicemail))->toBeTrue()
        ->and($notification->phoneNumber?->id)->toBe($call->phone_number_id)
        ->and($notification->contact?->displayName)->toBe('+16615551212')
        ->and($notification->metadata['provider_call_sid'])->toBe('CA'.str_repeat('7', 32))
        ->and($notification->metadata['provider_recording_sid'])->toBe('RE'.str_repeat('3', 32));
});

/** @return array<string, string> */
function teamNotifierInboundSmsPayload(): array
{
    return [
        'MessageSid' => 'SM'.str_repeat('2', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'Body' => 'Can you call me?',
        'NumSegments' => '1',
    ];
}

/** @param array<string, mixed> $overrides */
function teamNotifierCall(array $overrides = []): PhoneCall
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

    return PhoneCall::query()->create(array_merge([
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
        'status' => CallStatus::RINGING,
        'status_rank' => CallStatus::rank(CallStatus::RINGING),
        'started_at' => Carbon::parse('2026-06-10 16:58:00'),
    ], $overrides));
}

/** @param array<string, string> $overrides */
function teamNotifierDialStatusPayload(array $overrides = []): array
{
    return array_merge([
        'CallSid' => 'CA'.str_repeat('7', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'DialCallSid' => 'CA'.str_repeat('8', 32),
        'DialCallStatus' => 'completed',
        'DialCallDuration' => '42',
        'DialBridged' => 'true',
    ], $overrides);
}

/** @return array<string, string> */
function teamNotifierRecordingPayload(): array
{
    return [
        'RecordingSid' => 'RE'.str_repeat('3', 32),
        'CallSid' => 'CA'.str_repeat('7', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'RecordingStatus' => 'completed',
        'RecordingUrl' => 'https://api.twilio.com/recordings/RE333',
        'RecordingDuration' => '22',
        'RecordingChannels' => '1',
        'RecordingSource' => 'RecordVerb',
        'RecordingTrack' => 'inbound',
    ];
}
