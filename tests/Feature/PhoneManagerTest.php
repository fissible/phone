<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\Exceptions\PhoneMessageException;
use Fissible\Phone\Facades\Phone;
use Fissible\Phone\PhoneManager;
use Fissible\Phone\Twilio\TwilioClientFactory;
use Fissible\Phone\Twilio\TwilioPhoneProvider;
use Fissible\Phone\ValueObjects\OutboundMessage;

it('binds the phone manager and twilio provider', function (): void {
    expect(app(PhoneManager::class))->toBeInstanceOf(PhoneManager::class)
        ->and(app('phone'))->toBeInstanceOf(PhoneManager::class)
        ->and(app(PhoneProvider::class))->toBeInstanceOf(TwilioPhoneProvider::class);
});

it('can fake outbound messages without twilio credentials', function (): void {
    $fake = Phone::fake();

    $receipt = Phone::messages()
        ->to('+16615551212')
        ->body("We're on for this morning.")
        ->send();

    expect($receipt->provider)->toBe('fake')
        ->and($receipt->providerMessageSid)->toStartWith('SM')
        ->and($receipt->status)->toBe('sent')
        ->and($fake->messages())->toHaveCount(1)
        ->and($fake->messages()[0]->to)->toBe('+16615551212')
        ->and($fake->messages()[0]->body)->toBe("We're on for this morning.");
});

it('validates outbound messages before sending', function (): void {
    Phone::fake();

    Phone::messages()
        ->body('Missing recipient')
        ->send();
})->throws(PhoneMessageException::class, 'recipient');

it('requires a body or media before sending', function (): void {
    Phone::fake();

    Phone::messages()
        ->to('+16615551212')
        ->send();
})->throws(PhoneMessageException::class, 'body or at least one media URL');

it('fails clearly when twilio credentials are missing', function (): void {
    config()->set('phone.twilio.account_sid', null);
    config()->set('phone.twilio.auth_token', null);
    config()->set('phone.twilio.default_from', '+16615550100');

    Phone::messages()
        ->to('+16615551212')
        ->body('Hello')
        ->send();
})->throws(PhoneConfigurationException::class, 'Twilio credentials are missing');

it('fails clearly when no twilio sender is configured', function (): void {
    config()->set('phone.twilio.account_sid', 'AC'.str_repeat('1', 32));
    config()->set('phone.twilio.auth_token', 'secret');
    config()->set('phone.twilio.default_from', null);
    config()->set('phone.twilio.messaging_service_sid', null);

    app(TwilioClientFactory::class)->make();

    app(TwilioPhoneProvider::class)
        ->sendMessage(new OutboundMessage(
            to: '+16615551212',
            from: null,
            messagingServiceSid: null,
            body: 'Hello',
        ));
})->throws(PhoneConfigurationException::class, 'No Twilio sender is configured');
