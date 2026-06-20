<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Facades\Phone;
use Fissible\Phone\Jobs\SendOutboundCall;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\ValueObjects\OutboundCall;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Fissible\Phone\ValueObjects\ProviderCall;
use Fissible\Phone\ValueObjects\ProviderMessage;
use Illuminate\Support\Facades\Bus;

it('does not originate twice when the send job is retried after a sid is saved', function (): void {
    $fake = Phone::fake();

    $call = Phone::calls()
        ->to('+16615551212')
        ->from('+16615550100')
        ->twiml('<Response><Say>Hi.</Say></Response>')
        ->send();

    expect($call->status)->toBe(CallStatus::INITIATED)
        ->and($fake->calls())->toHaveCount(1);

    // Replay the job: the row already has a SID and is no longer queued.
    Bus::dispatchSync(new SendOutboundCall((int) $call->getKey()));

    expect($fake->calls())->toHaveCount(1)
        ->and(PhoneCall::query()->whereKey($call->getKey())->value('status'))->toBe(CallStatus::INITIATED);
});

it('marks an ambiguous provider failure as send_unknown without throwing', function (): void {
    app()->instance(PhoneProvider::class, new class implements PhoneProvider
    {
        public function sendMessage(OutboundMessage $message): ProviderMessage
        {
            throw new RuntimeException('not used');
        }

        public function createCall(OutboundCall $call): ProviderCall
        {
            throw new RuntimeException('provider timeout after possible accept');
        }
    });

    $call = Phone::calls()
        ->to('+16615551212')
        ->from('+16615550100')
        ->twiml('<Response><Say>Hi.</Say></Response>')
        ->send();

    expect($call->status)->toBe(CallStatus::SEND_UNKNOWN)
        ->and($call->provider_call_sid)->toBeNull()
        ->and($call->metadata['send_unknown']['message'])->toBe('provider timeout after possible accept');
});
