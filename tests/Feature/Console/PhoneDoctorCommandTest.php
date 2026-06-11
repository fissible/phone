<?php

declare(strict_types=1);

beforeEach(function (): void {
    config()->set('phone.provider', 'twilio');
    config()->set('phone.twilio.account_sid', 'AC'.str_repeat('1', 32));
    config()->set('phone.twilio.auth_token', 'secret');
    config()->set('phone.twilio.messaging_service_sid', 'MG'.str_repeat('2', 32));
    config()->set('phone.twilio.default_from', null);
    config()->set('phone.webhooks.base_url', 'https://example.com');
    config()->set('phone.webhooks.middleware', ['phone.twilio']);
    config()->set('phone.default_voice.forward_to', '+16615559999');
});

it('reports a healthy local phone configuration', function (): void {
    $this->artisan('phone:doctor')
        ->expectsOutput('Fissible Phone doctor')
        ->expectsOutput('[OK] Provider is twilio.')
        ->expectsOutput('[OK] Twilio credentials are configured.')
        ->expectsOutput('[OK] Webhook middleware is stateless.')
        ->expectsOutput('No configuration issues found.')
        ->assertExitCode(0);
});

it('fails when required twilio configuration or stateless middleware is missing', function (): void {
    config()->set('phone.twilio.account_sid', null);
    config()->set('phone.twilio.auth_token', null);
    config()->set('phone.webhooks.middleware', ['web', 'phone.twilio']);

    $this->artisan('phone:doctor')
        ->expectsOutput('Fissible Phone doctor')
        ->expectsOutput('[FAIL] Twilio credentials are missing. Set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN.')
        ->expectsOutput('[FAIL] Webhook middleware includes [web]. Twilio routes must stay stateless and out of CSRF/session middleware.')
        ->assertExitCode(1);
});
