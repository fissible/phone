<?php

declare(strict_types=1);

use Fissible\Phone\Events\MessageDeliveryUpdated;
use Fissible\Phone\Facades\Phone;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\MessageStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    config()->set('phone.twilio.default_from', '+16615550100');
    config()->set('phone.sms.allow_unknown_recipients', true);
    Phone::fake();
    Carbon::setTestNow(Carbon::parse('2026-06-10 13:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('updates outbound message delivery status from a twilio callback', function (): void {
    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Delivery tracked.')
        ->send();

    Event::fake([MessageDeliveryUpdated::class]);
    Carbon::setTestNow(Carbon::parse('2026-06-10 13:05:00'));

    $this->post('/phone/twilio/sms/status', statusCallbackPayload($message, [
        'MessageStatus' => 'delivered',
    ]))->assertNoContent();

    $message->refresh();
    $receipt = WebhookReceipt::query()->sole();

    expect($message->status)->toBe(MessageStatus::DELIVERED)
        ->and($message->status_rank)->toBe(MessageStatus::rank(MessageStatus::DELIVERED))
        ->and($message->delivered_at?->toDateTimeString())->toBe('2026-06-10 13:05:00')
        ->and($message->error_code)->toBeNull()
        ->and($message->error_message)->toBeNull()
        ->and($message->webhook_receipt_id)->toBe($receipt->id)
        ->and($message->metadata['twilio_status_callback']['provider_status'])->toBe('delivered')
        ->and($receipt->provider_sid)->toBe($message->provider_message_sid)
        ->and($receipt->processing_status)->toBe('processed');

    Event::assertDispatched(MessageDeliveryUpdated::class, function (MessageDeliveryUpdated $event) use ($message, $receipt): bool {
        return $event->message->is($message)
            && $event->oldStatus === MessageStatus::SENT
            && $event->newStatus === MessageStatus::DELIVERED
            && $event->providerStatus === 'delivered'
            && $event->webhookReceipt?->is($receipt);
    });
});

it('stores carrier failure details from a twilio status callback', function (): void {
    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Carrier failure tracked.')
        ->send();

    Event::fake([MessageDeliveryUpdated::class]);
    Carbon::setTestNow(Carbon::parse('2026-06-10 13:10:00'));

    $this->post('/phone/twilio/sms/status', statusCallbackPayload($message, [
        'MessageStatus' => 'undelivered',
        'ErrorCode' => '30005',
        'ErrorMessage' => 'Unknown destination handset',
    ]))->assertNoContent();

    $message->refresh();

    expect($message->status)->toBe(MessageStatus::UNDELIVERED)
        ->and($message->failed_at?->toDateTimeString())->toBe('2026-06-10 13:10:00')
        ->and($message->error_code)->toBe('30005')
        ->and($message->error_message)->toBe('Unknown destination handset')
        ->and($message->metadata['twilio_status_callback']['error_code'])->toBe('30005');

    Event::assertDispatched(MessageDeliveryUpdated::class, fn (MessageDeliveryUpdated $event): bool => $event->newStatus === MessageStatus::UNDELIVERED);
});

it('does not regress terminal message state from stale or conflicting callbacks', function (): void {
    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Terminal state.')
        ->send();

    $message->forceFill([
        'status' => MessageStatus::DELIVERED,
        'status_rank' => MessageStatus::rank(MessageStatus::DELIVERED),
        'delivered_at' => now(),
    ])->save();

    Event::fake([MessageDeliveryUpdated::class]);

    $this->post('/phone/twilio/sms/status', statusCallbackPayload($message, [
        'MessageStatus' => 'sent',
    ]))->assertNoContent();

    $this->post('/phone/twilio/sms/status', statusCallbackPayload($message, [
        'MessageStatus' => 'failed',
        'ErrorCode' => '30007',
    ]))->assertNoContent();

    expect($message->refresh()->status)->toBe(MessageStatus::DELIVERED)
        ->and($message->error_code)->toBeNull();

    Event::assertNotDispatched(MessageDeliveryUpdated::class);
});

it('acknowledges unmatched status callbacks without creating message records', function (): void {
    Event::fake([MessageDeliveryUpdated::class]);

    $this->post('/phone/twilio/sms/status', [
        'MessageSid' => 'SM'.str_repeat('9', 32),
        'MessageStatus' => 'delivered',
        'From' => '+16615550100',
        'To' => '+16615551212',
    ])->assertNoContent();

    expect(PhoneMessage::query()->count())->toBe(0)
        ->and(WebhookReceipt::query()->sole()->processing_status)->toBe('processed');

    Event::assertNotDispatched(MessageDeliveryUpdated::class);
});

it('does not apply lower-rank callbacks over a newer outbound state', function (): void {
    $message = Phone::messages()
        ->to('+16615551212')
        ->body('No stale queued callback.')
        ->send();

    Event::fake([MessageDeliveryUpdated::class]);

    $this->post('/phone/twilio/sms/status', statusCallbackPayload($message, [
        'MessageStatus' => 'queued',
    ]))->assertNoContent();

    expect($message->refresh()->status)->toBe(MessageStatus::SENT);

    Event::assertNotDispatched(MessageDeliveryUpdated::class);
});

/** @param  array<string, string>  $overrides */
function statusCallbackPayload(PhoneMessage $message, array $overrides = []): array
{
    return array_merge([
        'MessageSid' => (string) $message->provider_message_sid,
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'MessageStatus' => 'sent',
        'From' => (string) $message->from_number,
        'To' => $message->to_number,
    ], $overrides);
}
