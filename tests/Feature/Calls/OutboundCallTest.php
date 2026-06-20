<?php

declare(strict_types=1);

use Fissible\Phone\Events\OutboundCallFailed;
use Fissible\Phone\Events\OutboundCallInitiated;
use Fissible\Phone\Events\OutboundCallQueued;
use Fissible\Phone\Exceptions\PhoneCallException;
use Fissible\Phone\Facades\Phone;
use Fissible\Phone\Jobs\SendOutboundCall;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Support\CallStatus;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

it('originates an outbound call and persists it as an outbound phone call', function (): void {
    Event::fake([OutboundCallQueued::class, OutboundCallInitiated::class, OutboundCallFailed::class]);
    $fake = Phone::fake();

    $call = Phone::calls()
        ->to('+16615551212')
        ->from('+16615550100')
        ->twiml('<Response><Say>Hello from Acme.</Say></Response>')
        ->send();

    expect($call)->toBeInstanceOf(PhoneCall::class)
        ->and($call->direction)->toBe('outbound')
        ->and($call->from_number)->toBe('+16615550100')
        ->and($call->to_number)->toBe('+16615551212')
        ->and($call->status)->toBe(CallStatus::INITIATED)
        ->and($call->provider_call_sid)->toStartWith('CA')
        ->and($call->started_at)->not->toBeNull()
        ->and($fake->calls())->toHaveCount(1)
        ->and($fake->calls()[0]->twiml)->toBe('<Response><Say>Hello from Acme.</Say></Response>');

    Event::assertDispatched(OutboundCallQueued::class);
    Event::assertDispatched(OutboundCallInitiated::class, fn (OutboundCallInitiated $e): bool => $e->call->is($call));
    Event::assertNotDispatched(OutboundCallFailed::class);
});

it('queues an outbound call without originating immediately', function (): void {
    Bus::fake([SendOutboundCall::class]);
    Phone::fake();

    $call = Phone::calls()
        ->to('+16615551212')
        ->from('+16615550100')
        ->url('https://example.com/twiml')
        ->queue();

    expect($call->status)->toBe(CallStatus::QUEUED)
        ->and($call->provider_call_sid)->toBeNull();

    Bus::assertDispatched(SendOutboundCall::class);
});

it('requires twiml or a url', function (): void {
    Phone::fake();

    Phone::calls()->to('+16615551212')->from('+16615550100')->send();
})->throws(PhoneCallException::class);
