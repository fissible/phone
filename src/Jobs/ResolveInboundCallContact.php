<?php

declare(strict_types=1);

namespace Fissible\Phone\Jobs;

use Fissible\Phone\Contracts\ContactResolver;
use Fissible\Phone\Events\InboundCallContactResolved;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\ValueObjects\ContactLookup;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class ResolveInboundCallContact implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(
        public readonly int $callId,
        public readonly int $phoneNumberId,
    ) {}

    public function handle(ContactResolver $contacts, Dispatcher $events): ?PhoneCall
    {
        /** @var PhoneCall|null $call */
        $call = PhoneCall::query()->find($this->callId);

        /** @var PhoneNumber|null $phoneNumber */
        $phoneNumber = PhoneNumber::query()->find($this->phoneNumberId);

        if (! $call instanceof PhoneCall || ! $phoneNumber instanceof PhoneNumber) {
            return $call;
        }

        try {
            $contact = $contacts->resolve(new ContactLookup(
                channel: 'voice',
                direction: 'inbound',
                localNumber: $call->to_number,
                remoteNumber: $call->from_number,
                phoneNumber: $phoneNumber,
                call: $call,
                metadata: [
                    'provider_call_sid' => $call->provider_call_sid,
                    'provider_account_sid' => $call->provider_account_sid,
                ],
            ));
        } catch (Throwable $exception) {
            $this->markFailed($call, $exception);

            return $call->refresh();
        }

        if (! $contact->isResolved()) {
            return $call;
        }

        $call->forceFill([
            'metadata' => array_replace_recursive($call->metadata ?? [], [
                'contact' => $contact->toArray(),
                'contact_resolution' => [
                    'resolved_at' => now()->toISOString(),
                ],
            ]),
        ])->save();

        $call->refresh();
        $events->dispatch(new InboundCallContactResolved($call, $phoneNumber, $contact));

        return $call;
    }

    private function markFailed(PhoneCall $call, Throwable $exception): void
    {
        $call->forceFill([
            'metadata' => array_replace_recursive($call->metadata ?? [], [
                'contact_resolution' => [
                    'failed_at' => now()->toISOString(),
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]),
        ])->save();
    }
}
