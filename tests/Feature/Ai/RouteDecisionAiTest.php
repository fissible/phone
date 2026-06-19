<?php

declare(strict_types=1);

use Fissible\Phone\ValueObjects\ConversationRelayConfig;
use Fissible\Phone\ValueObjects\RouteDecision;

it('builds an ai route decision carrying the relay config', function (): void {
    $config = new ConversationRelayConfig(
        websocketUrl: 'wss://ai.example.com/relay',
        welcomeGreeting: 'Thanks for calling.',
    );

    $decision = RouteDecision::ai($config, metadata: ['handoff_reason' => 'after_hours']);

    expect($decision->type)->toBe(RouteDecision::AI)
        ->and($decision->conversationRelay)->toBe($config)
        ->and($decision->metadata)->toBe(['handoff_reason' => 'after_hours']);
});

it('round-trips an ai decision through toArray and fromArray', function (): void {
    $decision = RouteDecision::ai(new ConversationRelayConfig(
        websocketUrl: 'wss://ai.example.com/relay',
        voice: 'en-US-Neural',
        interruptible: false,
    ));

    $restored = RouteDecision::fromArray($decision->toArray());

    expect($restored->type)->toBe(RouteDecision::AI)
        ->and($restored->conversationRelay)->toEqual($decision->conversationRelay);
});

it('omits the relay config from toArray for non-ai decisions', function (): void {
    $array = RouteDecision::forward('+16615559999', 20)->toArray();

    expect($array)->not->toHaveKey('conversation_relay');
});
