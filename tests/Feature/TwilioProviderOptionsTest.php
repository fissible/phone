<?php

declare(strict_types=1);

use Fissible\Phone\Twilio\TwilioClientFactory;
use Fissible\Phone\Twilio\TwilioPhoneProvider;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Twilio\Rest\Client;

it('prefers an explicit messaging service sid over a from number', function (): void {
    $provider = new class(app(TwilioClientFactory::class), app('config')) extends TwilioPhoneProvider
    {
        /** @return array<string, mixed> */
        public function expose(OutboundMessage $message): array
        {
            $method = new ReflectionMethod(TwilioPhoneProvider::class, 'messageOptions');
            $method->setAccessible(true);

            return $method->invoke($this, $message);
        }
    };

    config()->set('phone.twilio.messaging_service_sid', 'MG'.str_repeat('1', 32));
    config()->set('phone.twilio.default_from', '+16615550100');

    $options = $provider->expose(new OutboundMessage(
        to: '+16615551212',
        from: '+16615550199',
        messagingServiceSid: 'MG'.str_repeat('2', 32),
        body: 'Hello',
    ));

    expect($options)
        ->toHaveKey('messagingServiceSid', 'MG'.str_repeat('2', 32))
        ->not->toHaveKey('from');
});

it('uses configured messaging service before configured from number', function (): void {
    $provider = new class(app(TwilioClientFactory::class), app('config')) extends TwilioPhoneProvider
    {
        /** @return array<string, mixed> */
        public function expose(OutboundMessage $message): array
        {
            $method = new ReflectionMethod(TwilioPhoneProvider::class, 'messageOptions');
            $method->setAccessible(true);

            return $method->invoke($this, $message);
        }
    };

    config()->set('phone.twilio.messaging_service_sid', 'MG'.str_repeat('1', 32));
    config()->set('phone.twilio.default_from', '+16615550100');

    $options = $provider->expose(new OutboundMessage(
        to: '+16615551212',
        from: null,
        messagingServiceSid: null,
        body: 'Hello',
    ));

    expect($options)
        ->toHaveKey('messagingServiceSid', 'MG'.str_repeat('1', 32))
        ->not->toHaveKey('from');
});

it('falls back to an explicit from number when no messaging service is present', function (): void {
    $provider = new class(app(TwilioClientFactory::class), app('config')) extends TwilioPhoneProvider
    {
        /** @return array<string, mixed> */
        public function expose(OutboundMessage $message): array
        {
            $method = new ReflectionMethod(TwilioPhoneProvider::class, 'messageOptions');
            $method->setAccessible(true);

            return $method->invoke($this, $message);
        }
    };

    config()->set('phone.twilio.messaging_service_sid', null);
    config()->set('phone.twilio.default_from', '+16615550100');

    $options = $provider->expose(new OutboundMessage(
        to: '+16615551212',
        from: '+16615550199',
        messagingServiceSid: null,
        body: 'Hello',
    ));

    expect($options)->toHaveKey('from', '+16615550199');
});

it('builds a twilio client from config', function (): void {
    config()->set('phone.twilio.account_sid', 'AC'.str_repeat('1', 32));
    config()->set('phone.twilio.auth_token', 'secret');

    expect(app(TwilioClientFactory::class)->make())->toBeInstanceOf(Client::class);
});
