<?php

declare(strict_types=1);

use Fissible\Phone\Events\AiSessionEnded;
use Fissible\Phone\Events\AiSessionFailed;
use Fissible\Phone\Models\PhoneAiSession;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    Carbon::setTestNow(Carbon::parse('2026-06-10 14:05:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function aiStatusSession(): PhoneAiSession
{
    $number = PhoneNumber::query()->create([
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    $call = PhoneCall::query()->create([
        'phone_number_id' => $number->id,
        'provider' => 'twilio',
        'provider_call_sid' => 'CA'.str_repeat('5', 32),
        'direction' => 'inbound',
        'from_number' => '+16615551212',
        'to_number' => '+16615550100',
        'status' => 'in_progress',
    ]);

    return PhoneAiSession::query()->create([
        'phone_call_id' => $call->id,
        'provider' => 'twilio',
        'mode' => 'conversation_relay',
        'status' => 'started',
        'websocket_url' => 'wss://ai.example.com/relay',
        'started_at' => now(),
    ]);
}

it('ends the ai session on a completed status callback', function (): void {
    Event::fake([AiSessionEnded::class, AiSessionFailed::class]);
    $session = aiStatusSession();

    $this->post('/phone/twilio/ai/status', [
        'CallSid' => 'CA'.str_repeat('5', 32),
        'SessionStatus' => 'completed',
        'SessionId' => 'VX'.str_repeat('2', 32),
    ])->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe('ended')
        ->and($session->ended_at)->not->toBeNull()
        ->and($session->provider_session_sid)->toBe('VX'.str_repeat('2', 32));

    Event::assertDispatched(AiSessionEnded::class, fn (AiSessionEnded $e): bool => $e->session->is($session));
    Event::assertNotDispatched(AiSessionFailed::class);
});

it('fails the ai session on a failed status callback', function (): void {
    Event::fake([AiSessionEnded::class, AiSessionFailed::class]);
    $session = aiStatusSession();

    $this->post('/phone/twilio/ai/status', [
        'CallSid' => 'CA'.str_repeat('5', 32),
        'SessionStatus' => 'failed',
    ])->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe('failed')
        ->and($session->ended_at)->not->toBeNull();

    Event::assertDispatched(AiSessionFailed::class, fn (AiSessionFailed $e): bool => $e->session->is($session));
    Event::assertNotDispatched(AiSessionEnded::class);
});
