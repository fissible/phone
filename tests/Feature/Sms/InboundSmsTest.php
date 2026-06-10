<?php

declare(strict_types=1);

use Fissible\Phone\Events\InboundMessageReceived;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Models\WebhookReceipt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    Carbon::setTestNow(Carbon::parse('2026-06-10 10:15:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('stores inbound sms in a local number thread and dispatches an event', function (): void {
    Event::fake([InboundMessageReceived::class]);

    $payload = inboundSmsPayload([
        'MessageSid' => 'SM'.str_repeat('1', 32),
        'SmsSid' => 'SM'.str_repeat('1', 32),
        'Body' => 'Crew is on site.',
    ]);

    $this->post('/phone/twilio/sms/inbound', $payload)->assertNoContent();

    $number = PhoneNumber::query()->sole();
    $thread = PhoneThread::query()->sole();
    $message = PhoneMessage::query()->sole();
    $receipt = WebhookReceipt::query()->sole();

    expect($number->phone_number)->toBe('+16615550100')
        ->and($number->scope_key)->toBe('global')
        ->and($number->provider_account_sid)->toBe('AC'.str_repeat('9', 32))
        ->and($thread->phone_number_id)->toBe($number->id)
        ->and($thread->local_number)->toBe('+16615550100')
        ->and($thread->remote_number)->toBe('+16615551212')
        ->and($thread->unread_count)->toBe(1)
        ->and($thread->last_inbound_message_at?->toDateTimeString())->toBe('2026-06-10 10:15:00')
        ->and($message->phone_thread_id)->toBe($thread->id)
        ->and($message->phone_number_id)->toBe($number->id)
        ->and($message->webhook_receipt_id)->toBe($receipt->id)
        ->and($message->provider_message_sid)->toBe($payload['MessageSid'])
        ->and($message->direction)->toBe('inbound')
        ->and($message->status)->toBe('received')
        ->and($message->from_number)->toBe('+16615551212')
        ->and($message->to_number)->toBe('+16615550100')
        ->and($message->body)->toBe('Crew is on site.')
        ->and($message->num_segments)->toBe(1)
        ->and($message->received_at?->toDateTimeString())->toBe('2026-06-10 10:15:00');

    Event::assertDispatched(InboundMessageReceived::class, function (InboundMessageReceived $event) use ($message, $thread, $number, $receipt): bool {
        return $event->message->is($message)
            && $event->thread->is($thread)
            && $event->phoneNumber->is($number)
            && $event->webhookReceipt?->is($receipt);
    });
});

it('uses the matched local number scope for inbound sms records', function (): void {
    PhoneNumber::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    $this->post('/phone/twilio/sms/inbound', inboundSmsPayload([
        'MessageSid' => 'SM'.str_repeat('2', 32),
    ]))->assertNoContent();

    expect(PhoneNumber::query()->count())->toBe(1)
        ->and(PhoneThread::query()->sole()->scope_key)->toBe('tenant:acme')
        ->and(PhoneThread::query()->sole()->scope_type)->toBe('tenant')
        ->and(PhoneThread::query()->sole()->scope_id)->toBe('acme')
        ->and(PhoneMessage::query()->sole()->scope_key)->toBe('tenant:acme')
        ->and(PhoneMessage::query()->sole()->scope_type)->toBe('tenant')
        ->and(PhoneMessage::query()->sole()->scope_id)->toBe('acme');
});

it('does not duplicate messages, unread counts, or events for retried inbound sms webhooks', function (): void {
    Event::fake([InboundMessageReceived::class]);

    $payload = inboundSmsPayload([
        'MessageSid' => 'SM'.str_repeat('3', 32),
        'Body' => 'Same webhook twice.',
    ]);

    $this->post('/phone/twilio/sms/inbound', $payload)->assertNoContent();
    $this->post('/phone/twilio/sms/inbound', $payload)->assertNoContent();

    expect(PhoneMessage::query()->count())->toBe(1)
        ->and(PhoneThread::query()->sole()->unread_count)->toBe(1)
        ->and(WebhookReceipt::query()->count())->toBe(1);

    Event::assertDispatchedTimes(InboundMessageReceived::class, 1);
});

it('stores inbound mms media metadata', function (): void {
    $payload = inboundSmsPayload([
        'MessageSid' => 'SM'.str_repeat('4', 32),
        'Body' => '',
        'NumMedia' => '2',
        'MediaUrl0' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Messages/SM/Media/ME0',
        'MediaContentType0' => 'image/jpeg',
        'MediaUrl1' => 'https://api.twilio.com/2010-04-01/Accounts/AC/Messages/SM/Media/ME1',
        'MediaContentType1' => 'application/pdf',
    ]);

    $this->post('/phone/twilio/sms/inbound', $payload)->assertNoContent();

    $message = PhoneMessage::query()->sole();

    expect($message->body)->toBeNull()
        ->and($message->media)->toBe([
            [
                'url' => $payload['MediaUrl0'],
                'content_type' => 'image/jpeg',
                'index' => 0,
            ],
            [
                'url' => $payload['MediaUrl1'],
                'content_type' => 'application/pdf',
                'index' => 1,
            ],
        ])
        ->and($message->metadata['twilio']['num_media'])->toBe(2);
});

/** @param  array<string, string>  $overrides */
function inboundSmsPayload(array $overrides = []): array
{
    return array_merge([
        'MessageSid' => 'SM'.str_repeat('0', 32),
        'SmsSid' => 'SM'.str_repeat('0', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'Body' => 'Hello',
        'NumSegments' => '1',
        'NumMedia' => '0',
        'ApiVersion' => '2010-04-01',
    ], $overrides);
}
