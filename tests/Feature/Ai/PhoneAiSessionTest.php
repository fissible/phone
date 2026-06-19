<?php

declare(strict_types=1);

use Fissible\Phone\Models\PhoneAiSession;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Illuminate\Support\Carbon;

it('persists an ai session with documented attributes, defaults, and casts', function (): void {
    $session = PhoneAiSession::query()->create([
        'mode' => 'conversation_relay',
        'status' => 'started',
        'provider_session_sid' => 'AI'.str_repeat('1', 32),
        'websocket_url' => 'wss://ai.example.com/relay',
        'started_at' => Carbon::parse('2026-06-19 17:00:00'),
        'ended_at' => Carbon::parse('2026-06-19 17:03:00'),
        'transcript' => 'Caller: hello. Agent: hi.',
        'summary' => 'Caller asked about hours.',
        'handoff_reason' => 'after_hours',
        'metadata' => ['voice' => 'en-US-Neural'],
    ]);

    $fresh = $session->fresh();

    expect($fresh->scope_key)->toBe('global')
        ->and($fresh->provider)->toBe('twilio')
        ->and($fresh->mode)->toBe('conversation_relay')
        ->and($fresh->status)->toBe('started')
        ->and($fresh->provider_session_sid)->toBe('AI'.str_repeat('1', 32))
        ->and($fresh->websocket_url)->toBe('wss://ai.example.com/relay')
        ->and($fresh->started_at)->toBeInstanceOf(Carbon::class)
        ->and($fresh->ended_at)->toBeInstanceOf(Carbon::class)
        ->and($fresh->transcript)->toBe('Caller: hello. Agent: hi.')
        ->and($fresh->summary)->toBe('Caller asked about hours.')
        ->and($fresh->handoff_reason)->toBe('after_hours')
        ->and($fresh->metadata)->toBe(['voice' => 'en-US-Neural']);
});

it('belongs to a call', function (): void {
    $number = PhoneNumber::query()->create([
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    $call = PhoneCall::query()->create([
        'phone_number_id' => $number->id,
        'direction' => 'inbound',
        'from_number' => '+16615551212',
        'to_number' => '+16615550100',
        'status' => 'in_progress',
    ]);

    $session = PhoneAiSession::query()->create([
        'phone_call_id' => $call->id,
        'mode' => 'conversation_relay',
        'status' => 'started',
    ]);

    expect($session->call)->toBeInstanceOf(PhoneCall::class)
        ->and($session->call->id)->toBe($call->id);
});
