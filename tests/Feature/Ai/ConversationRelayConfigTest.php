<?php

declare(strict_types=1);

use Fissible\Phone\ValueObjects\ConversationRelayConfig;

it('exposes documented attributes with sensible defaults', function (): void {
    $config = new ConversationRelayConfig(websocketUrl: 'wss://ai.example.com/relay');

    expect($config->websocketUrl)->toBe('wss://ai.example.com/relay')
        ->and($config->voice)->toBeNull()
        ->and($config->language)->toBeNull()
        ->and($config->welcomeGreeting)->toBeNull()
        ->and($config->interruptible)->toBeTrue()
        ->and($config->parameters)->toBe([])
        ->and($config->attributes)->toBe([])
        ->and($config->metadata)->toBe([]);
});

it('round-trips through toArray and fromArray', function (): void {
    $config = new ConversationRelayConfig(
        websocketUrl: 'wss://ai.example.com/relay',
        voice: 'en-US-Neural',
        language: 'en-US',
        welcomeGreeting: 'Thanks for calling Acme.',
        interruptible: false,
        parameters: ['token' => 'signed-abc'],
        attributes: ['ttsProvider' => 'ElevenLabs'],
        metadata: ['session_ref' => 'sess_1'],
    );

    $restored = ConversationRelayConfig::fromArray($config->toArray());

    expect($restored)->toEqual($config);
});

it('rebuilds from a minimal array using defaults', function (): void {
    $restored = ConversationRelayConfig::fromArray(['websocket_url' => 'wss://ai.example.com/relay']);

    expect($restored->websocketUrl)->toBe('wss://ai.example.com/relay')
        ->and($restored->interruptible)->toBeTrue()
        ->and($restored->voice)->toBeNull()
        ->and($restored->parameters)->toBe([]);
});
