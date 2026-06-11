<?php

declare(strict_types=1);

use Fissible\Phone\Contracts\ActivityLogger;
use Fissible\Phone\Contracts\ContactResolver;
use Fissible\Phone\Events\InboundCallContactResolved;
use Fissible\Phone\Jobs\ResolveInboundCallContact;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\ContactLookup;
use Fissible\Phone\ValueObjects\PhoneActivity;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

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
        ->and($thread->remote_display_name)->toBe('Jane Customer')
        ->and($thread->contact_type)->toBe('customer')
        ->and($thread->contact_id)->toBe('cust_123')
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

it('dispatches inbound voice contact resolution after the twiml response path', function (): void {
    Bus::fake([ResolveInboundCallContact::class]);

    app()->instance(ContactResolver::class, new class implements ContactResolver
    {
        public function resolve(ContactLookup $lookup): ContactIdentity
        {
            throw new RuntimeException('resolver should not run inline');
        }
    });

    $this->post('/phone/twilio/voice/inbound', contactInboundVoicePayload())->assertOk();

    $call = PhoneCall::query()->sole();
    $number = PhoneNumber::query()->sole();

    expect($call->metadata['contact'] ?? null)->toBeNull();

    Bus::assertDispatchedAfterResponse(
        ResolveInboundCallContact::class,
        fn (ResolveInboundCallContact $job): bool => $job->callId === $call->id
            && $job->phoneNumberId === $number->id,
    );
});

it('resolves inbound voice contacts in the deferred job', function (): void {
    Event::fake([InboundCallContactResolved::class]);

    $number = contactPhoneNumber();
    $call = contactPhoneCall($number);

    app()->instance(ContactResolver::class, new class implements ContactResolver
    {
        public function resolve(ContactLookup $lookup): ContactIdentity
        {
            expect($lookup->channel)->toBe('voice')
                ->and($lookup->direction)->toBe('inbound')
                ->and($lookup->localNumber)->toBe('+16615550100')
                ->and($lookup->remoteNumber)->toBe('+16615551212')
                ->and($lookup->call)->toBeInstanceOf(PhoneCall::class);

            return new ContactIdentity(
                displayName: 'Sam Lead',
                externalType: 'lead',
                externalId: 'lead_123',
                url: 'https://example.com/leads/lead_123',
                metadata: ['pipeline' => 'jobsite-cameras'],
            );
        }
    });

    (new ResolveInboundCallContact((int) $call->id, (int) $number->id))->handle(
        app(ContactResolver::class),
        app(Dispatcher::class),
    );

    $call->refresh();

    expect($call->metadata['contact']['display_name'])->toBe('Sam Lead')
        ->and($call->metadata['contact']['external_type'])->toBe('lead')
        ->and($call->metadata['contact']['external_id'])->toBe('lead_123')
        ->and($call->metadata['contact']['metadata']['pipeline'])->toBe('jobsite-cameras')
        ->and($call->metadata['contact_resolution']['resolved_at'])->toBeString();

    Event::assertDispatched(InboundCallContactResolved::class, function (InboundCallContactResolved $event) use ($call, $number): bool {
        return $event->call->is($call)
            && $event->phoneNumber->is($number)
            && $event->contact->externalId === 'lead_123';
    });
});

it('records inbound voice contact resolution failures without throwing', function (): void {
    $number = contactPhoneNumber();
    $call = contactPhoneCall($number);

    app()->instance(ContactResolver::class, new class implements ContactResolver
    {
        public function resolve(ContactLookup $lookup): ContactIdentity
        {
            throw new RuntimeException('CRM timeout');
        }
    });

    (new ResolveInboundCallContact((int) $call->id, (int) $number->id))->handle(
        app(ContactResolver::class),
        app(Dispatcher::class),
    );

    $call->refresh();

    expect($call->metadata['contact'] ?? null)->toBeNull()
        ->and($call->metadata['contact_resolution']['failed_at'])->toBeString()
        ->and($call->metadata['contact_resolution']['exception'])->toBe(RuntimeException::class)
        ->and($call->metadata['contact_resolution']['message'])->toBe('CRM timeout');
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

function contactPhoneNumber(): PhoneNumber
{
    return PhoneNumber::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number' => '+16615550100',
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'status' => 'active',
    ]);
}

function contactPhoneCall(PhoneNumber $number): PhoneCall
{
    return PhoneCall::query()->create([
        'scope_key' => 'global',
        'provider' => 'twilio',
        'phone_number_id' => $number->id,
        'provider_call_sid' => 'CA'.str_repeat('2', 32),
        'provider_account_sid' => 'AC'.str_repeat('9', 32),
        'direction' => 'inbound',
        'from_number' => '+16615551212',
        'to_number' => '+16615550100',
        'status' => 'ringing',
        'status_rank' => 2,
        'started_at' => now(),
        'metadata' => [
            'twilio' => [
                'CallSid' => 'CA'.str_repeat('2', 32),
            ],
        ],
    ]);
}
