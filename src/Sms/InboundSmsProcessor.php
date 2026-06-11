<?php

declare(strict_types=1);

namespace Fissible\Phone\Sms;

use DateTimeInterface;
use Fissible\Phone\Contracts\ActivityLogger;
use Fissible\Phone\Contracts\OptOutPolicy;
use Fissible\Phone\Contracts\PhoneNumberResolver;
use Fissible\Phone\Contracts\TeamNotifier;
use Fissible\Phone\Events\InboundMessageReceived;
use Fissible\Phone\Events\ThreadOptedIn;
use Fissible\Phone\Events\ThreadOptedOut;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Services\SmsThreadResolver;
use Fissible\Phone\Twilio\TwilioInboundSmsPayload;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\OptOutResult;
use Fissible\Phone\ValueObjects\PhoneActivity;
use Fissible\Phone\ValueObjects\TeamNotification;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboundSmsProcessor
{
    public function __construct(
        private readonly PhoneNumberResolver $phoneNumbers,
        private readonly SmsThreadResolver $threads,
        private readonly OptOutPolicy $optOutPolicy,
        private readonly ActivityLogger $activity,
        private readonly TeamNotifier $notifier,
        private readonly Dispatcher $events,
    ) {}

    public function processTwilio(Request $request, ?WebhookReceipt $receipt = null): PhoneMessage
    {
        $payload = TwilioInboundSmsPayload::fromRequest($request);
        $created = false;
        $optOutResult = null;

        /** @var PhoneMessage $message */
        $message = DB::transaction(function () use ($payload, $receipt, &$created, &$optOutResult): PhoneMessage {
            $phoneNumber = $this->phoneNumbers->resolveForInbound($payload->to, $payload->accountSid);
            $thread = $this->threads->resolveInbound($phoneNumber, $payload);

            $existing = PhoneMessage::query()
                ->where('provider', 'twilio')
                ->where('provider_message_sid', $payload->messageSid)
                ->first();

            if ($existing instanceof PhoneMessage) {
                return $existing;
            }

            $created = true;
            $receivedAt = now();

            $message = PhoneMessage::query()->create([
                'scope_key' => $phoneNumber->scope_key,
                'scope_type' => $phoneNumber->scope_type,
                'scope_id' => $phoneNumber->scope_id,
                'provider' => 'twilio',
                'phone_thread_id' => $thread->getKey(),
                'phone_number_id' => $phoneNumber->getKey(),
                'webhook_receipt_id' => $receipt?->getKey(),
                'provider_message_sid' => $payload->messageSid,
                'provider_account_sid' => $payload->accountSid,
                'direction' => 'inbound',
                'from_number' => $payload->from,
                'to_number' => $payload->to,
                'body' => $payload->body,
                'media' => $payload->media,
                'num_segments' => $payload->numSegments,
                'status' => 'received',
                'status_rank' => 0,
                'received_at' => $receivedAt,
                'metadata' => $payload->metadata,
            ]);

            $this->touchThreadForInbound($thread, $receivedAt);
            $optOutResult = $this->optOutPolicy->applyInbound($thread, $message);

            return $message;
        });

        if ($created) {
            $message->refresh();
            $thread = $message->thread()->firstOrFail();
            $phoneNumber = $message->phoneNumber()->firstOrFail();

            $this->dispatchOptOutEvent($optOutResult, $thread, $message);

            $this->events->dispatch(new InboundMessageReceived(
                message: $message,
                thread: $thread,
                phoneNumber: $phoneNumber,
                webhookReceipt: $receipt,
            ));

            $this->activity->log(new PhoneActivity(
                type: 'sms.inbound',
                channel: 'sms',
                direction: 'inbound',
                occurredAt: $message->received_at ?? now(),
                phoneNumber: $phoneNumber,
                thread: $thread,
                message: $message,
                contact: $this->contactFromThread($thread, $message->from_number),
                webhookReceipt: $receipt,
                metadata: [
                    'provider_message_sid' => $message->provider_message_sid,
                ],
            ));

            $this->notifier->notify(new TeamNotification(
                type: 'sms.inbound',
                channel: 'sms',
                occurredAt: $message->received_at ?? now(),
                direction: 'inbound',
                phoneNumber: $phoneNumber,
                thread: $thread,
                message: $message,
                contact: $this->contactFromThread($thread, $message->from_number),
                webhookReceipt: $receipt,
                metadata: [
                    'provider_message_sid' => $message->provider_message_sid,
                ],
            ));
        }

        return $message;
    }

    private function contactFromThread(PhoneThread $thread, ?string $remoteNumber): ContactIdentity
    {
        $contact = $thread->metadata['contact'] ?? null;

        if (is_array($contact)) {
            return ContactIdentity::fromArray($contact);
        }

        return ContactIdentity::anonymous($remoteNumber ?? $thread->remote_number);
    }

    private function dispatchOptOutEvent(?OptOutResult $result, PhoneThread $thread, PhoneMessage $message): void
    {
        if ($result?->isOptOut()) {
            $this->events->dispatch(new ThreadOptedOut($thread, $message, $result->keyword));

            return;
        }

        if ($result?->isOptIn()) {
            $this->events->dispatch(new ThreadOptedIn($thread, $message, $result->keyword));
        }
    }

    private function touchThreadForInbound(PhoneThread $thread, DateTimeInterface $receivedAt): void
    {
        PhoneThread::query()
            ->whereKey($thread->getKey())
            ->update([
                'last_message_at' => $receivedAt,
                'last_inbound_message_at' => $receivedAt,
                'unread_count' => DB::raw('unread_count + 1'),
                'updated_at' => $receivedAt,
            ]);
    }
}
