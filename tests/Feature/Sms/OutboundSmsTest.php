<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Events\OutboundMessageFailed;
use Fissible\Phone\Events\OutboundMessageQueued;
use Fissible\Phone\Events\OutboundMessageSent;
use Fissible\Phone\Exceptions\PhoneMessageException;
use Fissible\Phone\Facades\Phone;
use Fissible\Phone\Jobs\SendOutboundMessage;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Fissible\Phone\ValueObjects\ProviderMessage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-10 11:30:00'));
    config()->set('phone.twilio.default_from', '+16615550100');
    config()->set('phone.twilio.messaging_service_sid', null);
    config()->set('phone.sms.allow_unknown_recipients', true);
    config()->set('phone.webhooks.base_url', 'https://example.com');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('persists and synchronously sends outbound sms through the provider', function (): void {
    Event::fake([
        OutboundMessageQueued::class,
        OutboundMessageSent::class,
    ]);
    $fake = Phone::fake();

    $message = Phone::messages()
        ->to('+16615551212')
        ->body("We're on for this morning.")
        ->send();

    $number = PhoneNumber::query()->sole();
    $thread = PhoneThread::query()->sole();

    expect($message->exists)->toBeTrue()
        ->and($message->provider)->toBe('twilio')
        ->and($message->direction)->toBe('outbound')
        ->and($message->from_number)->toBe('+16615550100')
        ->and($message->to_number)->toBe('+16615551212')
        ->and($message->body)->toBe("We're on for this morning.")
        ->and($message->status)->toBe('sent')
        ->and($message->provider_message_sid)->toStartWith('SM')
        ->and($message->queued_at?->toDateTimeString())->toBe('2026-06-10 11:30:00')
        ->and($message->sent_at?->toDateTimeString())->toBe('2026-06-10 11:30:00')
        ->and($message->phone_number_id)->toBe($number->id)
        ->and($message->phone_thread_id)->toBe($thread->id)
        ->and($thread->last_outbound_message_at?->toDateTimeString())->toBe('2026-06-10 11:30:00')
        ->and($fake->messages())->toHaveCount(1)
        ->and($fake->messages()[0]->statusCallbackUrl)->toBe('https://example.com/phone/twilio/sms/status');

    Event::assertDispatched(OutboundMessageQueued::class, fn (OutboundMessageQueued $event): bool => $event->message->is($message));
    Event::assertDispatched(OutboundMessageSent::class, fn (OutboundMessageSent $event): bool => $event->message->is($message));
});

it('can queue outbound sms without immediately sending when the bus is faked', function (): void {
    Bus::fake();
    Phone::fake();

    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Queued reminder.')
        ->queue();

    expect($message->status)->toBe('queued')
        ->and($message->provider_message_sid)->toBeNull()
        ->and(PhoneMessage::query()->sole()->status)->toBe('queued');

    Bus::assertDispatched(SendOutboundMessage::class, fn (SendOutboundMessage $job): bool => $job->messageId === $message->id);
});

it('stores outbound contact attribution on messages and threads', function (): void {
    $fake = Phone::fake();

    $message = Phone::messages()
        ->to('+16615551212')
        ->body("We're on for this morning.")
        ->contact(
            type: 'lead',
            id: 123,
            name: 'Sam Lead',
            url: 'https://example.com/leads/123',
            metadata: ['source' => 'mesabit'],
        )
        ->send();

    $thread = PhoneThread::query()->sole();

    expect($message->metadata['contact']['display_name'])->toBe('Sam Lead')
        ->and($message->metadata['contact']['external_type'])->toBe('lead')
        ->and($message->metadata['contact']['external_id'])->toBe('123')
        ->and($message->metadata['contact']['url'])->toBe('https://example.com/leads/123')
        ->and($message->metadata['contact']['metadata']['source'])->toBe('mesabit')
        ->and($thread->remote_display_name)->toBe('Sam Lead')
        ->and($thread->contact_type)->toBe('lead')
        ->and($thread->contact_id)->toBe('123')
        ->and($thread->metadata['contact']['display_name'])->toBe('Sam Lead')
        ->and($fake->messages())->toHaveCount(1)
        ->and($fake->messages()[0]->contact?->externalType)->toBe('lead')
        ->and($fake->messages()[0]->contact?->externalId)->toBe('123');
});

it('can send through a messaging service without a known from number', function (): void {
    config()->set('phone.twilio.default_from', null);
    config()->set('phone.twilio.messaging_service_sid', 'MG'.str_repeat('1', 32));
    $fake = Phone::fake();

    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Messaging service send.')
        ->send();

    expect($message->status)->toBe('sent')
        ->and($message->from_number)->toBeNull()
        ->and($message->phone_number_id)->toBeNull()
        ->and($message->phone_thread_id)->toBeNull()
        ->and(PhoneNumber::query()->count())->toBe(0)
        ->and(PhoneThread::query()->count())->toBe(0)
        ->and($fake->messages())->toHaveCount(1)
        ->and($fake->messages()[0]->messagingServiceSid)->toBe('MG'.str_repeat('1', 32));
});

it('does not send again when a sent outbound job is retried', function (): void {
    $fake = Phone::fake();

    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Retry-safe reminder.')
        ->send();

    (new SendOutboundMessage((int) $message->id))->handle(
        app(PhoneProvider::class),
        app(Dispatcher::class),
    );

    expect($fake->messages())->toHaveCount(1)
        ->and($message->refresh()->status)->toBe('sent');
});

it('does not send queued rows that already have a provider sid', function (): void {
    $fake = Phone::fake();

    $message = PhoneMessage::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'direction' => 'outbound',
        'from_number' => '+16615550100',
        'to_number' => '+16615551212',
        'body' => 'Already accepted.',
        'status' => 'queued',
        'status_rank' => 1,
        'provider_message_sid' => 'SM'.str_repeat('9', 32),
        'queued_at' => now(),
    ]);

    (new SendOutboundMessage((int) $message->id))->handle(
        app(PhoneProvider::class),
        app(Dispatcher::class),
    );

    expect($fake->messages())->toHaveCount(0)
        ->and($message->refresh()->status)->toBe('queued')
        ->and($message->provider_message_sid)->toBe('SM'.str_repeat('9', 32));
});

it('marks unexpected provider failures as send_unknown without throwing', function (): void {
    Event::fake([OutboundMessageFailed::class]);
    app()->instance(PhoneProvider::class, new class implements PhoneProvider
    {
        public function sendMessage(OutboundMessage $message): ProviderMessage
        {
            throw new RuntimeException('provider timeout after possible accept');
        }
    });

    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Ambiguous send.')
        ->send();

    expect($message->status)->toBe('send_unknown')
        ->and($message->provider_message_sid)->toBeNull()
        ->and($message->error_message)->toBe('provider timeout after possible accept')
        ->and($message->metadata['send_unknown']['exception'])->toBe(RuntimeException::class);

    Event::assertDispatched(OutboundMessageFailed::class, fn (OutboundMessageFailed $event): bool => $event->message->is($message));
});

it('blocks unknown recipients by default', function (): void {
    config()->set('phone.sms.allow_unknown_recipients', false);
    $fake = Phone::fake();

    expect(fn (): PhoneMessage => Phone::messages()
        ->to('+16615551212')
        ->body('Blocked unknown recipient.')
        ->send()
    )->toThrow(PhoneMessageException::class, 'recipient is unknown');

    expect(PhoneMessage::query()->count())->toBe(0)
        ->and(PhoneThread::query()->count())->toBe(0)
        ->and($fake->messages())->toHaveCount(0);
});

it('allows unknown recipients when explicitly requested on the outbound message', function (): void {
    config()->set('phone.sms.allow_unknown_recipients', false);
    $fake = Phone::fake();

    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Explicit unknown send.')
        ->allowUnknownRecipient()
        ->send();

    expect($message->status)->toBe('sent')
        ->and($message->metadata['policy']['allow_unknown_recipient'])->toBeTrue()
        ->and($fake->messages())->toHaveCount(1);
});

it('allows outbound messages to an existing non opted-out thread when unknown sends are disabled', function (): void {
    config()->set('phone.sms.allow_unknown_recipients', false);
    $fake = Phone::fake();

    $number = PhoneNumber::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    PhoneThread::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number_id' => $number->id,
        'local_number' => '+16615550100',
        'remote_number' => '+16615551212',
    ]);

    $message = Phone::messages()
        ->to('+16615551212')
        ->body('Known recipient.')
        ->send();

    expect($message->status)->toBe('sent')
        ->and($fake->messages())->toHaveCount(1);
});

it('blocks outbound messages to opted-out threads', function (): void {
    $fake = Phone::fake();

    $number = PhoneNumber::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    PhoneThread::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number_id' => $number->id,
        'local_number' => '+16615550100',
        'remote_number' => '+16615551212',
        'opted_out_at' => now(),
    ]);

    expect(fn (): PhoneMessage => Phone::messages()
        ->to('+16615551212')
        ->body('Blocked opted-out recipient.')
        ->send()
    )->toThrow(PhoneMessageException::class, 'thread is opted out');

    expect(PhoneMessage::query()->count())->toBe(0)
        ->and($fake->messages())->toHaveCount(0);
});

it('blocks messaging-service sends when an opted-out thread exists for the recipient', function (): void {
    config()->set('phone.twilio.default_from', null);
    config()->set('phone.twilio.messaging_service_sid', 'MG'.str_repeat('2', 32));
    config()->set('phone.sms.allow_unknown_recipients', true);
    $fake = Phone::fake();

    $number = PhoneNumber::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    PhoneThread::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number_id' => $number->id,
        'local_number' => '+16615550100',
        'remote_number' => '+16615551212',
        'opted_out_at' => now(),
    ]);

    expect(fn (): PhoneMessage => Phone::messages()
        ->to('+16615551212')
        ->body('Blocked opted-out recipient.')
        ->send()
    )->toThrow(PhoneMessageException::class, 'thread is opted out');

    expect(PhoneMessage::query()->count())->toBe(0)
        ->and($fake->messages())->toHaveCount(0);
});
