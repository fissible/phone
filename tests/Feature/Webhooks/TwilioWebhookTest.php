<?php

declare(strict_types=1);

use Fissible\Phone\Models\WebhookReceipt;
use Twilio\Security\RequestValidator;

beforeEach(function (): void {
    config()->set('phone.twilio.auth_token', 'test-token');
    config()->set('phone.twilio.validate_webhooks', true);
    config()->set('phone.webhooks.base_url', 'https://mesabit.net');
});

it('validates form webhook signatures against the configured public base url and query string', function (): void {
    $params = [
        'MessageSid' => 'SM'.str_repeat('1', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'Body' => 'Crew is on site.',
    ];
    $url = 'https://mesabit.net/phone/twilio/sms/inbound?source=twilio';
    $signature = (new RequestValidator('test-token'))->computeSignature($url, $params);

    $this->post('/phone/twilio/sms/inbound?source=twilio', $params, [
        'X-Twilio-Signature' => $signature,
    ])->assertNoContent();

    $receipt = WebhookReceipt::query()->sole();

    expect($receipt->event_type)->toBe('sms.inbound')
        ->and($receipt->provider_sid)->toBe($params['MessageSid'])
        ->and($receipt->request_url)->toBe($url)
        ->and($receipt->signature_valid)->toBeTrue()
        ->and($receipt->processing_status)->toBe('processed')
        ->and($receipt->payload['Body'])->toBe('Crew is on site.')
        ->and($receipt->payload['_query'])->toBe(['source' => 'twilio']);
});

it('stores a minimal rejected receipt when the twilio signature is invalid', function (): void {
    $this->post('/phone/twilio/sms/inbound', [
        'MessageSid' => 'SM'.str_repeat('2', 32),
        'Body' => 'Invalid attempt',
    ], [
        'X-Twilio-Signature' => 'invalid',
    ])->assertForbidden();

    $receipt = WebhookReceipt::query()->sole();

    expect($receipt->event_type)->toBe('sms.inbound')
        ->and($receipt->provider_sid)->toBe('SM'.str_repeat('2', 32))
        ->and($receipt->signature_valid)->toBeFalse()
        ->and($receipt->processing_status)->toBe('rejected')
        ->and($receipt->payload)->toBeNull();
});

it('validates json webhook signatures using bodySHA256 instead of form parameter sorting', function (): void {
    $body = json_encode([
        'CallSid' => 'CA'.str_repeat('3', 32),
        'Status' => 'completed',
    ], JSON_THROW_ON_ERROR);
    $bodyHash = RequestValidator::computeBodyHash($body);
    $url = 'https://mesabit.net/phone/twilio/ai/status?bodySHA256='.$bodyHash.'&source=relay';
    $signature = (new RequestValidator('test-token'))->computeSignature($url);

    $this->call('POST', '/phone/twilio/ai/status?bodySHA256='.$bodyHash.'&source=relay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TWILIO_SIGNATURE' => $signature,
    ], $body)->assertNoContent();

    $receipt = WebhookReceipt::query()->sole();

    expect($receipt->event_type)->toBe('ai.status')
        ->and($receipt->provider_sid)->toBe('CA'.str_repeat('3', 32))
        ->and($receipt->request_url)->toBe($url)
        ->and($receipt->signature_valid)->toBeTrue()
        ->and($receipt->payload['Status'])->toBe('completed')
        ->and($receipt->payload['_query']['bodySHA256'])->toBe($bodyHash);
});

it('does not require csrf middleware for the package twilio routes', function (): void {
    config()->set('phone.twilio.validate_webhooks', false);

    $this->post('/phone/twilio/sms/status', [
        'MessageSid' => 'SM'.str_repeat('4', 32),
        'MessageStatus' => 'delivered',
    ])->assertNoContent();

    expect(WebhookReceipt::query()->sole()->processing_status)->toBe('processed');
});

it('deduplicates exact webhook retries by request hash', function (): void {
    config()->set('phone.twilio.validate_webhooks', false);

    $payload = [
        'MessageSid' => 'SM'.str_repeat('5', 32),
        'MessageStatus' => 'delivered',
    ];

    $this->post('/phone/twilio/sms/status', $payload)->assertNoContent();
    $this->post('/phone/twilio/sms/status', $payload)->assertNoContent();

    expect(WebhookReceipt::query()->count())->toBe(1)
        ->and(WebhookReceipt::query()->sole()->processing_status)->toBe('processed');
});
