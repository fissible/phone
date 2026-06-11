<?php

declare(strict_types=1);

use Fissible\Phone\Events\CallStatusUpdated;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\CallStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    Carbon::setTestNow(Carbon::parse('2026-06-10 15:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('updates an existing call from a twilio voice status callback', function (): void {
    Event::fake([CallStatusUpdated::class]);

    $call = voiceCallbackCall([
        'status' => CallStatus::RINGING,
        'status_rank' => CallStatus::rank(CallStatus::RINGING),
    ]);

    $response = $this->post('/phone/twilio/voice/status', voiceStatusCallbackPayload([
        'CallStatus' => 'in-progress',
        'SequenceNumber' => '2',
        'AnsweredBy' => 'human',
    ]));

    $response->assertNoContent();

    $call->refresh();
    $receipt = WebhookReceipt::query()->where('event_type', 'voice.status')->sole();

    expect($call->status)->toBe(CallStatus::IN_PROGRESS)
        ->and($call->status_rank)->toBe(CallStatus::rank(CallStatus::IN_PROGRESS))
        ->and($call->provider_sequence_number)->toBe(2)
        ->and($call->answered_by)->toBe('human')
        ->and($call->answered_at?->toDateTimeString())->toBe('2026-06-10 15:00:00')
        ->and($call->webhook_receipt_id)->toBe($receipt->id)
        ->and($call->metadata['twilio_status_callback']['provider_status'])->toBe('in-progress')
        ->and($receipt->processing_status)->toBe('processed');

    Event::assertDispatched(CallStatusUpdated::class, function (CallStatusUpdated $event) use ($call, $receipt): bool {
        return $event->call->is($call)
            && $event->oldStatus === CallStatus::RINGING
            && $event->newStatus === CallStatus::IN_PROGRESS
            && $event->providerStatus === 'in-progress'
            && $event->webhookReceipt?->is($receipt);
    });
});

it('does not regress a terminal call status from stale callbacks', function (): void {
    Event::fake([CallStatusUpdated::class]);

    $call = voiceCallbackCall([
        'status' => CallStatus::COMPLETED,
        'status_rank' => CallStatus::rank(CallStatus::COMPLETED),
        'duration_seconds' => 120,
        'ended_at' => Carbon::parse('2026-06-10 14:55:00'),
    ]);

    $this->post('/phone/twilio/voice/status', voiceStatusCallbackPayload([
        'CallStatus' => 'ringing',
        'SequenceNumber' => '1',
    ]))->assertNoContent();

    $call->refresh();

    expect($call->status)->toBe(CallStatus::COMPLETED)
        ->and($call->status_rank)->toBe(CallStatus::rank(CallStatus::COMPLETED))
        ->and($call->duration_seconds)->toBe(120)
        ->and($call->ended_at?->toDateTimeString())->toBe('2026-06-10 14:55:00')
        ->and(WebhookReceipt::query()->where('event_type', 'voice.status')->sole()->processing_status)->toBe('processed');

    Event::assertNotDispatched(CallStatusUpdated::class);
});

it('updates the call from a dial action status callback and returns valid twiml', function (): void {
    Event::fake([CallStatusUpdated::class]);

    $call = voiceCallbackCall([
        'status' => CallStatus::RINGING,
        'status_rank' => CallStatus::rank(CallStatus::RINGING),
    ]);

    $response = $this->post('/phone/twilio/voice/dial-status?call_id='.$call->id, voiceDialStatusCallbackPayload([
        'DialCallStatus' => 'no-answer',
        'DialCallDuration' => '18',
        'DialBridged' => 'false',
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/xml')
        ->and(dialStatusXml($response->getContent())->getName())->toBe('Response');

    $call->refresh();
    $receipt = WebhookReceipt::query()->where('event_type', 'voice.dial_status')->sole();

    expect($call->status)->toBe(CallStatus::NO_ANSWER)
        ->and($call->status_rank)->toBe(CallStatus::rank(CallStatus::NO_ANSWER))
        ->and($call->duration_seconds)->toBe(18)
        ->and($call->ended_at?->toDateTimeString())->toBe('2026-06-10 15:00:00')
        ->and($call->metadata['twilio_dial_status_callback']['dial_call_sid'])->toBe('CA'.str_repeat('8', 32))
        ->and($call->metadata['twilio_dial_status_callback']['dial_bridged'])->toBeFalse()
        ->and($receipt->provider_sid)->toBe('CA'.str_repeat('7', 32));

    Event::assertDispatched(CallStatusUpdated::class, function (CallStatusUpdated $event) use ($call, $receipt): bool {
        return $event->call->is($call)
            && $event->oldStatus === CallStatus::RINGING
            && $event->newStatus === CallStatus::NO_ANSWER
            && $event->providerStatus === 'no-answer'
            && $event->webhookReceipt?->is($receipt);
    });
});

/** @param array<string, mixed> $overrides */
function voiceCallbackCall(array $overrides = []): PhoneCall
{
    return PhoneCall::query()->create(array_merge([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'provider_call_sid' => 'CA'.str_repeat('7', 32),
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'direction' => 'inbound',
        'from_number' => '+16615551212',
        'to_number' => '+16615550100',
        'status' => CallStatus::RINGING,
        'status_rank' => CallStatus::rank(CallStatus::RINGING),
        'started_at' => Carbon::parse('2026-06-10 14:58:00'),
    ], $overrides));
}

/** @param array<string, string> $overrides */
function voiceStatusCallbackPayload(array $overrides = []): array
{
    return array_merge([
        'CallSid' => 'CA'.str_repeat('7', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'CallStatus' => 'completed',
        'Direction' => 'inbound',
        'CallDuration' => '42',
    ], $overrides);
}

/** @param array<string, string> $overrides */
function voiceDialStatusCallbackPayload(array $overrides = []): array
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

function dialStatusXml(string $content): SimpleXMLElement
{
    $xml = simplexml_load_string($content);

    expect($xml)->toBeInstanceOf(SimpleXMLElement::class);

    return $xml;
}
