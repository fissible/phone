<?php

declare(strict_types=1);

use Fissible\Phone\Twilio\TwilioVoiceTwiMLBuilder;
use Fissible\Phone\ValueObjects\ConversationRelayConfig;
use Fissible\Phone\ValueObjects\RouteDecision;

it('builds connect conversation relay twiml from an ai decision', function (): void {
    $decision = RouteDecision::ai(new ConversationRelayConfig(
        websocketUrl: 'wss://ai.example.com/relay',
        voice: 'en-US-Neural',
        language: 'en-US',
        welcomeGreeting: 'Thanks for calling Acme.',
        interruptible: false,
        parameters: ['token' => 'signed-abc'],
        attributes: ['ttsProvider' => 'ElevenLabs'],
    ));

    $xml = (new TwilioVoiceTwiMLBuilder)->build($decision);

    expect($xml)->toContain('<Connect>')
        ->and($xml)->toContain('<ConversationRelay')
        ->and($xml)->toContain('url="wss://ai.example.com/relay"')
        ->and($xml)->toContain('voice="en-US-Neural"')
        ->and($xml)->toContain('language="en-US"')
        ->and($xml)->toContain('welcomeGreeting="Thanks for calling Acme."')
        ->and($xml)->toContain('interruptible="false"')
        ->and($xml)->toContain('ttsProvider="ElevenLabs"')
        ->and($xml)->toContain('<Parameter name="token" value="signed-abc"');
});

it('hangs up when an ai decision has no relay config', function (): void {
    $decision = new RouteDecision(type: RouteDecision::AI);

    $xml = (new TwilioVoiceTwiMLBuilder)->build($decision);

    expect($xml)->toContain('<Hangup')
        ->and($xml)->not->toContain('<ConversationRelay');
});
