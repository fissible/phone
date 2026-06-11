<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\ContactResolver;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Twilio\TwilioInboundSmsPayload;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\ContactLookup;

class SmsThreadResolver
{
    public function __construct(
        private readonly ContactResolver $contacts,
    ) {}

    public function resolveInbound(PhoneNumber $phoneNumber, TwilioInboundSmsPayload $payload): PhoneThread
    {
        /** @var PhoneThread $thread */
        $thread = PhoneThread::query()->firstOrCreate([
            'scope_key' => $phoneNumber->scope_key,
            'phone_number_id' => $phoneNumber->getKey(),
            'remote_number' => $payload->from,
        ], [
            'scope_type' => $phoneNumber->scope_type,
            'scope_id' => $phoneNumber->scope_id,
            'provider' => 'twilio',
            'local_number' => $payload->to,
            'metadata' => [],
        ]);

        $contact = $this->contacts->resolve(new ContactLookup(
            channel: 'sms',
            direction: 'inbound',
            localNumber: $payload->to,
            remoteNumber: $payload->from,
            phoneNumber: $phoneNumber,
            thread: $thread,
            metadata: $payload->metadata,
        ));

        if ($contact->isResolved()) {
            $this->applyContact($thread, $contact);
        }

        return $thread->refresh();
    }

    private function applyContact(PhoneThread $thread, ContactIdentity $contact): void
    {
        $thread->forceFill([
            'remote_display_name' => $contact->displayName,
            'contact_type' => $contact->externalType,
            'contact_id' => $contact->externalId,
            'metadata' => array_replace($thread->metadata ?? [], [
                'contact' => $contact->toArray(),
            ]),
        ])->save();
    }
}
