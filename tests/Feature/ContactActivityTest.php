<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\ActivityLogger;
use Fissible\Phone\Contracts\ContactResolver;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\ContactLookup;
use Fissible\Phone\ValueObjects\PhoneActivity;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config()->set('phone.twilio.validate_webhooks', false);
    config()->set('phone.webhooks.base_url', 'https://example.com');
    config()->set('phone.default_voice.forward_to', '+16615559999');
    Carbon::setTestNow(Carbon::parse('2026-06-10 14:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('lets host apps resolve inbound sms contacts and log activity', function (): void {
    $logger = new class implements ActivityLogger
    {
        /** @var list<PhoneActivity> */
        public array $activities = [];

        public function log(PhoneActivity $activity): void
        {
            $this->activities[] = $activity;
        }
    };

    app()->instance(ActivityLogger::class, $logger);
    app()->instance(ContactResolver::class, new class implements ContactResolver
    {
        public function resolve(ContactLookup $lookup): ContactIdentity
        {
            expect($lookup->channel)->toBe('sms')
                ->and($lookup->direction)->toBe('inbound')
                ->and($lookup->localNumber)->toBe('+16615550100')
                ->and($lookup->remoteNumber)->toBe('+16615551212')
                ->and($lookup->scopeKey())->toBe('global');

            return new ContactIdentity(
                displayName: 'Jane Customer',
                externalType: 'customer',
                externalId: 'cust_123',
                url: 'https://example.com/customers/cust_123',
                metadata: ['source' => 'test-crm'],
            );
        }
    });

    $this->post('/phone/twilio/sms/inbound', contactInboundSmsPayload())->assertNoContent();

    $thread = PhoneThread::query()->sole();
    $message = PhoneMessage::query()->sole();
    $activity = $logger->activities[0] ?? null;

    expect($thread->metadata['contact']['display_name'])->toBe('Jane Customer')
        ->and($thread->metadata['contact']['external_type'])->toBe('customer')
        ->and($thread->metadata['contact']['external_id'])->toBe('cust_123')
        ->and($thread->metadata['contact']['url'])->toBe('https://example.com/customers/cust_123')
        ->and($thread->metadata['contact']['metadata']['source'])->toBe('test-crm')
        ->and($logger->activities)->toHaveCount(1)
        ->and($activity)->toBeInstanceOf(PhoneActivity::class)
        ->and($activity->type)->toBe('sms.inbound')
        ->and($activity->channel)->toBe('sms')
        ->and($activity->message?->is($message))->toBeTrue()
        ->and($activity->thread?->is($thread))->toBeTrue()
        ->and($activity->contact?->displayName)->toBe('Jane Customer')
        ->and($activity->contact?->externalId)->toBe('cust_123')
        ->and($activity->metadata['provider_message_sid'])->toBe('SM'.str_repeat('1', 32));
});

it('logs inbound voice activity without resolving contacts in the twiml path', function (): void {
    $logger = new class implements ActivityLogger
    {
        /** @var list<PhoneActivity> */
        public array $activities = [];

        public function log(PhoneActivity $activity): void
        {
            $this->activities[] = $activity;
        }
    };

    app()->instance(ActivityLogger::class, $logger);

    $this->post('/phone/twilio/voice/inbound', contactInboundVoicePayload())->assertOk();

    $call = PhoneCall::query()->sole();
    $activity = $logger->activities[0] ?? null;

    expect($logger->activities)->toHaveCount(1)
        ->and($activity)->toBeInstanceOf(PhoneActivity::class)
        ->and($activity->type)->toBe('voice.inbound')
        ->and($activity->channel)->toBe('voice')
        ->and($activity->call?->is($call))->toBeTrue()
        ->and($activity->contact?->displayName)->toBe('+16615551212')
        ->and($activity->contact?->isResolved())->toBeFalse()
        ->and($activity->metadata['provider_call_sid'])->toBe('CA'.str_repeat('1', 32));
});

/** @return array<string, string> */
function contactInboundSmsPayload(): array
{
    return [
        'MessageSid' => 'SM'.str_repeat('1', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'Body' => 'Hello',
        'NumSegments' => '1',
    ];
}

/** @return array<string, string> */
function contactInboundVoicePayload(): array
{
    return [
        'CallSid' => 'CA'.str_repeat('1', 32),
        'AccountSid' => 'AC'.str_repeat('9', 32),
        'From' => '+16615551212',
        'To' => '+16615550100',
        'CallStatus' => 'ringing',
        'Direction' => 'inbound',
        'ApiVersion' => '2010-04-01',
    ];
}
