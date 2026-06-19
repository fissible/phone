<?php

declare(strict_types=1);

use Fissible\Phone\Jobs\PruneWebhookReceipts;
use Fissible\Phone\Models\WebhookReceipt;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config()->set('phone.retention.webhook_receipts_days', 90);
    config()->set('phone.retention.raw_payload_days', 30);
    Carbon::setTestNow(Carbon::parse('2026-06-19 12:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function pruneReceipt(string $hash, int $ageDays): WebhookReceipt
{
    $receipt = WebhookReceipt::query()->create([
        'provider' => 'twilio',
        'event_type' => 'sms.inbound',
        'request_method' => 'POST',
        'request_url' => 'https://example.com/phone/twilio/sms/inbound',
        'signature_valid' => true,
        'request_hash' => $hash,
        'processing_status' => 'processed',
        'payload' => ['Body' => 'hi'],
        'headers' => ['X-Test' => 'y'],
    ]);

    $receipt->forceFill(['created_at' => now()->subDays($ageDays)])->saveQuietly();

    return $receipt->refresh();
}

it('deletes expired receipts and strips raw payloads from mid-aged ones', function (): void {
    $recent = pruneReceipt('recent', 10);
    $midAged = pruneReceipt('mid', 40);
    $expired = pruneReceipt('expired', 100);

    $result = (new PruneWebhookReceipts)->handle(config());

    expect($result)->toBe(['deleted' => 1, 'stripped' => 1])
        ->and(WebhookReceipt::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(WebhookReceipt::query()->whereKey($recent->id)->value('payload'))->not->toBeNull();

    $midAged->refresh();
    expect($midAged->payload)->toBeNull()
        ->and($midAged->headers)->toBeNull()
        ->and($midAged->processing_status)->toBe('processed');
});

it('prunes receipts through the phone:prune command', function (): void {
    pruneReceipt('old', 100);
    $mid = pruneReceipt('mid', 40);

    $this->artisan('phone:prune')
        ->assertExitCode(0)
        ->expectsOutputToContain('Deleted 1 expired webhook receipt(s); stripped raw payloads from 1 receipt(s).');

    expect(WebhookReceipt::query()->count())->toBe(1)
        ->and($mid->refresh()->payload)->toBeNull();
});

it('does nothing when retention windows are disabled', function (): void {
    config()->set('phone.retention.webhook_receipts_days', 0);
    config()->set('phone.retention.raw_payload_days', 0);
    pruneReceipt('keep', 500);

    $result = (new PruneWebhookReceipts)->handle(config());

    expect($result)->toBe(['deleted' => 0, 'stripped' => 0])
        ->and(WebhookReceipt::query()->count())->toBe(1);
});
