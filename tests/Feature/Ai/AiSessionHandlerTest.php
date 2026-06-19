<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\AiSessionHandler;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Services\DisabledAiSessionHandler;
use Fissible\Phone\Twilio\TwilioInboundVoicePayload;
use Fissible\Phone\ValueObjects\CallContext;

function aiCallContext(): CallContext
{
    $payload = new TwilioInboundVoicePayload(
        callSid: 'CA'.str_repeat('9', 32),
        parentCallSid: null,
        accountSid: null,
        from: '+16615551212',
        to: '+16615550100',
        callStatus: 'in_progress',
        direction: 'inbound',
        sequenceNumber: null,
        raw: [],
    );

    return new CallContext(new PhoneCall, new PhoneNumber, $payload);
}

it('resolves the disabled ai handler by default', function (): void {
    expect(app(AiSessionHandler::class))->toBeInstanceOf(DisabledAiSessionHandler::class);
});

it('does not handle calls when disabled', function (): void {
    expect(app(AiSessionHandler::class)->shouldHandle(aiCallContext()))->toBeFalse();
});

it('throws when configuring a disabled handler', function (): void {
    app(AiSessionHandler::class)->configure(aiCallContext());
})->throws(PhoneConfigurationException::class);
