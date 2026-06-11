<?php

declare(strict_types=1);

use Fissible\Phone\Events\CallRouteDecided;
use Fissible\Phone\Events\InboundCallReceived;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\WebhookReceipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    config()->set('phone.webhooks.base_url', 'https://example.com');
    config()->set('phone.default_voice.forward_to', '+16615559999');
    config()->set('phone.default_voice.timeout', 18);
    Carbon::setTestNow(Carbon::parse('2026-06-10 14:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('persists an inbound call and returns forwarding twiml for the matched number', function (): void {
    Event::fake([
        InboundCallReceived::class,
        CallRouteDecided::class,
    ]);

    $number = PhoneNumber::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'routing_mode' => 'forward',
        'forward_to' => '+16615558888',
        'status' => 'active',
    ]);

    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('1', 32),
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/xml');

    $xml = voiceXml($response->getContent());
    $dial = $xml->Dial;
    $call = PhoneCall::query()->sole();
    $receipt = WebhookReceipt::query()->sole();

    expect((string) $dial)->toBe('+16615558888')
        ->and((string) $dial['action'])->toBe('https://example.com/phone/twilio/voice/dial-status?call_id='.$call->id)
        ->and((string) $dial['method'])->toBe('POST')
        ->and((string) $dial['timeout'])->toBe('18')
        ->and((string) $dial['answerOnBridge'])->toBe('true')
        ->and($call->phone_number_id)->toBe($number->id)
        ->and($call->webhook_receipt_id)->toBe($receipt->id)
        ->and($call->scope_key)->toBe('tenant:acme')
        ->and($call->provider_call_sid)->toBe('CA'.str_repeat('1', 32))
        ->and($call->provider_account_sid)->toBe('AC'.str_repeat('9', 32))
        ->and($call->direction)->toBe('inbound')
        ->and($call->from_number)->toBe('+16615551212')
        ->and($call->to_number)->toBe('+16615550100')
        ->and($call->status)->toBe('ringing')
        ->and($call->routing_mode)->toBe('forward')
        ->and($call->route_decision['type'])->toBe('forward')
        ->and($call->route_decision['forward_to'])->toBe('+16615558888')
        ->and($call->started_at?->toDateTimeString())->toBe('2026-06-10 14:00:00')
        ->and($receipt->processing_status)->toBe('processed');

    Event::assertDispatched(InboundCallReceived::class, function (InboundCallReceived $event) use ($call, $number, $receipt): bool {
        return $event->call->is($call)
            && $event->phoneNumber->is($number)
            && $event->webhookReceipt?->is($receipt);
    });

    Event::assertDispatched(CallRouteDecided::class, function (CallRouteDecided $event) use ($call, $number): bool {
        return $event->call->is($call)
            && $event->phoneNumber->is($number)
            && $event->decision->type === 'forward';
    });
});

it('uses the default forward number for an unknown inbound local number', function (): void {
    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('2', 32),
    ]));

    $response->assertOk();

    $xml = voiceXml($response->getContent());

    expect((string) $xml->Dial)->toBe('+16615559999')
        ->and(PhoneNumber::query()->sole()->phone_number)->toBe('+16615550100')
        ->and(PhoneCall::query()->sole()->phone_number_id)->toBe(PhoneNumber::query()->sole()->id);
});

it('falls back to voicemail twiml when no forward destination is configured', function (): void {
    config()->set('phone.default_voice.forward_to', null);
    config()->set('phone.default_voice.voicemail_greeting', 'We missed your call. Leave a message.');

    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('3', 32),
    ]));

    $response->assertOk();

    $xml = voiceXml($response->getContent());
    $record = $xml->Record;
    $call = PhoneCall::query()->sole();

    expect((string) $xml->Say)->toBe('We missed your call. Leave a message.')
        ->and((string) $record['recordingStatusCallback'])->toBe('https://example.com/phone/twilio/voice/recording?call_id='.$call->id.'&purpose=voicemail')
        ->and((string) $record['recordingStatusCallbackMethod'])->toBe('POST')
        ->and((string) $record['playBeep'])->toBe('true')
        ->and($call->routing_mode)->toBe('voicemail')
        ->and($call->route_decision['type'])->toBe('voicemail');
});

it('can opt into twilio voicemail transcription callbacks', function (): void {
    config()->set('phone.default_voice.forward_to', null);
    config()->set('phone.default_voice.transcribe_voicemails', true);

    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('5', 32),
    ]));

    $response->assertOk();

    $xml = voiceXml($response->getContent());
    $record = $xml->Record;
    $call = PhoneCall::query()->sole();

    expect((string) $record['transcribe'])->toBe('true')
        ->and((string) $record['transcribeCallback'])->toBe('https://example.com/phone/twilio/voice/transcription?call_id='.$call->id.'&purpose=voicemail')
        ->and($call->route_decision['transcribe'])->toBeTrue()
        ->and($call->route_decision['transcription_callback_url'])->toBe('https://example.com/phone/twilio/voice/transcription?call_id='.$call->id.'&purpose=voicemail');
});

it('forwards calls during configured business hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-10 10:15:00', 'America/Los_Angeles'));
    config()->set('phone.business_hours', [
        'timezone' => 'America/Los_Angeles',
        'weekly' => [
            'wednesday' => [
                ['start' => '09:00', 'end' => '17:00'],
            ],
        ],
        'holidays' => [],
    ]);

    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('6', 32),
    ]));

    $response->assertOk();

    $xml = voiceXml($response->getContent());
    $call = PhoneCall::query()->sole();

    expect((string) $xml->Dial)->toBe('+16615559999')
        ->and($call->route_decision['type'])->toBe('forward');
});

it('uses after-hours voicemail outside configured business hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-10 18:15:00', 'America/Los_Angeles'));
    config()->set('phone.default_voice.after_hours_mode', 'voicemail');
    config()->set('phone.business_hours', [
        'timezone' => 'America/Los_Angeles',
        'weekly' => [
            'wednesday' => [
                ['start' => '09:00', 'end' => '17:00'],
            ],
        ],
        'holidays' => [],
    ]);

    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('7', 32),
    ]));

    $response->assertOk();

    $xml = voiceXml($response->getContent());
    $call = PhoneCall::query()->sole();

    expect((string) $xml->Say)->toBe('Please leave a message after the tone.')
        ->and((string) $xml->Record['recordingStatusCallback'])->toBe('https://example.com/phone/twilio/voice/recording?call_id='.$call->id.'&purpose=voicemail')
        ->and($call->routing_mode)->toBe('voicemail')
        ->and($call->route_decision['type'])->toBe('voicemail');
});

it('allows phone number business hours to override global hours', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-10 10:15:00', 'America/Los_Angeles'));
    config()->set('phone.business_hours', [
        'timezone' => 'America/Los_Angeles',
        'weekly' => [
            'wednesday' => false,
        ],
        'holidays' => [],
    ]);

    PhoneNumber::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'routing_mode' => 'forward',
        'forward_to' => '+16615558888',
        'business_hours' => [
            'timezone' => 'America/Los_Angeles',
            'weekly' => [
                'wednesday' => '09:00-17:00',
            ],
        ],
        'status' => 'active',
    ]);

    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('8', 32),
    ]));

    $response->assertOk();

    $xml = voiceXml($response->getContent());
    $call = PhoneCall::query()->sole();

    expect((string) $xml->Dial)->toBe('+16615558888')
        ->and($call->route_decision['type'])->toBe('forward');
});

it('uses after-hours mode on configured holidays', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-10 10:15:00', 'America/Los_Angeles'));
    config()->set('phone.default_voice.after_hours_mode', 'voicemail');
    config()->set('phone.business_hours', [
        'timezone' => 'America/Los_Angeles',
        'weekly' => [
            'wednesday' => [
                ['start' => '09:00', 'end' => '17:00'],
            ],
        ],
        'holidays' => [
            '2026-06-10',
        ],
    ]);

    $response = $this->post('/phone/twilio/voice/inbound', inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('9', 32),
    ]));

    $response->assertOk();

    $xml = voiceXml($response->getContent());

    expect((string) $xml->Say)->toBe('Please leave a message after the tone.')
        ->and(PhoneCall::query()->sole()->route_decision['type'])->toBe('voicemail');
});

it('does not duplicate calls or events when twilio retries the same inbound voice webhook', function (): void {
    Event::fake([
        InboundCallReceived::class,
        CallRouteDecided::class,
    ]);

    $payload = inboundVoicePayload([
        'CallSid' => 'CA'.str_repeat('4', 32),
    ]);

    $this->post('/phone/twilio/voice/inbound', $payload)->assertOk();
    $this->post('/phone/twilio/voice/inbound', $payload)->assertOk();

    expect(PhoneCall::query()->count())->toBe(1)
        ->and(WebhookReceipt::query()->count())->toBe(1);

    Event::assertDispatchedTimes(InboundCallReceived::class, 1);
    Event::assertDispatchedTimes(CallRouteDecided::class, 1);
});

/** @param array<string, string> $overrides */
function inboundVoicePayload(array $overrides = []): array
{
    return array_merge([
        'CallSid' => 'CA'.str_repeat('0', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'CallStatus' => 'ringing',
        'Direction' => 'inbound',
        'ApiVersion' => '2010-04-01',
    ], $overrides);
}

function voiceXml(string $content): SimpleXMLElement
{
    $xml = simplexml_load_string($content);

    expect($xml)->toBeInstanceOf(SimpleXMLElement::class);

    return $xml;
}
