<?php

declare(strict_types=1);

use Fissible\Phone\Testing\FakePhoneProvider;
use Fissible\Phone\ValueObjects\OutboundCall;

it('records originated calls and returns a provider call receipt', function (): void {
    $fake = new FakePhoneProvider;

    $call = new OutboundCall(
        to: '+16615551212',
        from: '+16615550100',
        twiml: '<Response><Say>Hello from Acme.</Say></Response>',
    );

    $receipt = $fake->createCall($call);

    expect($receipt->provider)->toBe('fake')
        ->and($receipt->providerCallSid)->toStartWith('CA')
        ->and($receipt->status)->toBe('queued')
        ->and($fake->calls())->toHaveCount(1)
        ->and($fake->calls()[0])->toBe($call)
        ->and($fake->callReceipts())->toHaveCount(1);
});
