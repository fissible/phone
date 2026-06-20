<?php

declare(strict_types=1);

use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\Twilio\TwilioClientFactory;
use Fissible\Phone\Twilio\TwilioPhoneProvider;
use Fissible\Phone\ValueObjects\OutboundCall;

function exposingCallProvider(): TwilioPhoneProvider
{
    return new class(app(TwilioClientFactory::class), app('config')) extends TwilioPhoneProvider
    {
        /** @return array<string, mixed> */
        public function expose(OutboundCall $call): array
        {
            $method = new ReflectionMethod(TwilioPhoneProvider::class, 'callOptions');
            $method->setAccessible(true);

            return $method->invoke($this, $call);
        }
    };
}

it('builds twilio call options from an outbound call', function (): void {
    $options = exposingCallProvider()->expose(new OutboundCall(
        to: '+16615551212',
        from: '+16615550100',
        twiml: '<Response><Say>Hi.</Say></Response>',
        statusCallbackUrl: 'https://example.com/phone/twilio/voice/status',
        machineDetection: 'Enable',
        timeout: 30,
    ));

    expect($options)
        ->toHaveKey('twiml', '<Response><Say>Hi.</Say></Response>')
        ->toHaveKey('statusCallback', 'https://example.com/phone/twilio/voice/status')
        ->toHaveKey('statusCallbackMethod', 'POST')
        ->toHaveKey('machineDetection', 'Enable')
        ->toHaveKey('timeout', 30)
        ->not->toHaveKey('url');
});

it('throws when no caller id or default from is configured', function (): void {
    config()->set('phone.twilio.default_from', null);

    exposingCallProvider()->createCall(new OutboundCall(
        to: '+16615551212',
        from: null,
        twiml: '<Response><Say>Hi.</Say></Response>',
    ));
})->throws(PhoneConfigurationException::class);
