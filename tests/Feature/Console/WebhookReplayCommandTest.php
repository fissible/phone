<?php

declare(strict_types=1);

use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\WebhookReceipt;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
});

function replayableReceipt(): WebhookReceipt
{
    return WebhookReceipt::query()->create([
        'provider' => 'twilio',
        'event_type' => 'sms.inbound',
        'request_method' => 'POST',
        'request_url' => 'https://example.com/phone/twilio/sms/inbound',
        'request_hash' => 'replay-hash',
        'signature_valid' => true,
        'processing_status' => 'failed',
        'failed_at' => now(),
        'error_class' => 'RuntimeException',
        'error_message' => 'boom',
        'payload' => [
            'MessageSid' => 'SM'.str_repeat('7', 32),
            'AccountSid' => 'AC'.str_repeat('9', 32),
            'From' => '+16615551212',
            'To' => '+16615550100',
            'Body' => 'Replay me',
            'NumMedia' => '0',
        ],
    ]);
}

it('reprocesses a failed receipt and marks it processed', function (): void {
    $receipt = replayableReceipt();

    $this->artisan('phone:webhook:replay', ['receipt' => $receipt->id])
        ->assertExitCode(0)
        ->expectsOutputToContain('Replayed webhook receipt');

    $receipt->refresh();

    expect(PhoneMessage::query()->where('provider_message_sid', 'SM'.str_repeat('7', 32))->exists())->toBeTrue()
        ->and($receipt->processing_status)->toBe('processed')
        ->and($receipt->replay_count)->toBe(1)
        ->and($receipt->failed_at)->toBeNull();
});

it('fails cleanly for an unknown receipt id', function (): void {
    $this->artisan('phone:webhook:replay', ['receipt' => 999])
        ->assertExitCode(1)
        ->expectsOutputToContain('not found');
});
