<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\AiSessionHandler;
use Fissible\Phone\Events\AiSessionStarted;
use Fissible\Phone\Models\PhoneAiSession;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\ValueObjects\CallContext;
use Fissible\Phone\ValueObjects\ConversationRelayConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    config()->set('phone.webhooks.base_url', 'https://example.com');
    Carbon::setTestNow(Carbon::parse('2026-06-10 14:00:00'));

    app()->bind(AiSessionHandler::class, fn (): AiSessionHandler => new class implements AiSessionHandler
    {
        public function shouldHandle(CallContext $call): bool
        {
            return true;
        }

        public function configure(CallContext $call): ConversationRelayConfig
        {
            return new ConversationRelayConfig(
                websocketUrl: 'wss://ai.example.com/relay',
                welcomeGreeting: 'Thanks for calling Acme.',
            );
        }
    });
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('routes an inbound call to ai handoff, persists a session, and emits AiSessionStarted', function (): void {
    Event::fake([AiSessionStarted::class]);

    PhoneNumber::query()->create([
        'scope_key' => 'tenant:acme',
        'scope_type' => 'tenant',
        'scope_id' => 'acme',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    $response = $this->post('/phone/twilio/voice/inbound', [
        'CallSid' => 'CA'.str_repeat('5', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'CallStatus' => 'ringing',
        'Direction' => 'inbound',
    ]);

    $response->assertOk();

    $xml = $response->getContent();
    $call = PhoneCall::query()->sole();
    $session = PhoneAiSession::query()->sole();

    expect($xml)->toContain('<ConversationRelay')
        ->and($xml)->toContain('url="wss://ai.example.com/relay"')
        ->and($call->routing_mode)->toBe('ai')
        ->and($call->route_decision['type'])->toBe('ai')
        ->and($session->phone_call_id)->toBe($call->id)
        ->and($session->scope_key)->toBe('tenant:acme')
        ->and($session->mode)->toBe('conversation_relay')
        ->and($session->status)->toBe('started')
        ->and($session->websocket_url)->toBe('wss://ai.example.com/relay')
        ->and($session->started_at)->not->toBeNull();

    Event::assertDispatched(AiSessionStarted::class, fn (AiSessionStarted $e): bool => $e->session->is($session));
});
