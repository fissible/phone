<?php

declare(strict_types=1);

namespace Fissible\Phone\Sms;

use DateTimeInterface;
use Fissible\Phone\Contracts\MessagePolicy;
use Fissible\Phone\Contracts\ScopeResolver;
use Fissible\Phone\Events\OutboundMessageQueued;
use Fissible\Phone\Exceptions\PhoneMessageException;
use Fissible\Phone\Jobs\SendOutboundMessage;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Support\MessageStatus;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\DB;

class OutboundMessageService
{
    public function __construct(
        private readonly Repository $config,
        private readonly ScopeResolver $scopeResolver,
        private readonly MessagePolicy $messagePolicy,
        private readonly Dispatcher $bus,
        private readonly EventDispatcher $events,
    ) {}

    public function send(OutboundMessage $message): PhoneMessage
    {
        $record = $this->create($message);

        $this->bus->dispatchSync(new SendOutboundMessage((int) $record->getKey()));

        return $record->refresh();
    }

    public function queue(OutboundMessage $message): PhoneMessage
    {
        $record = $this->create($message);

        $this->bus->dispatch(new SendOutboundMessage((int) $record->getKey()));

        return $record->refresh();
    }

    public function create(OutboundMessage $message): PhoneMessage
    {
        $this->validate($message);

        $scope = $this->scopeResolver->resolve();
        $from = $message->from ?: $this->stringConfig('phone.twilio.default_from');
        $messagingServiceSid = $message->messagingServiceSid
            ?: $this->stringConfig('phone.twilio.messaging_service_sid');
        $statusCallbackUrl = $message->statusCallbackUrl ?: $this->defaultStatusCallbackUrl();
        $queuedAt = now();

        /** @var PhoneMessage $record */
        $record = DB::transaction(function () use ($message, $scope, $from, $messagingServiceSid, $statusCallbackUrl, $queuedAt): PhoneMessage {
            $phoneNumber = $this->resolveLocalNumber($from, $scope->key, $scope->type, $scope->id);
            $thread = $phoneNumber instanceof PhoneNumber
                ? $this->findThread($phoneNumber, $message->to)
                : $this->findThreadForScope($scope->key, $message->to);

            $this->messagePolicy->assertCanSend($message, $phoneNumber, $thread);

            if ($phoneNumber instanceof PhoneNumber) {
                $thread ??= $this->createThread($phoneNumber, $message->to);
            }

            if ($thread instanceof PhoneThread) {
                if ($message->contact instanceof ContactIdentity) {
                    $this->applyThreadContact($thread, $message->contact);
                }

                $this->touchThreadForOutbound($thread, $queuedAt);
            }

            $scopeKey = $thread?->scope_key ?? $phoneNumber?->scope_key ?? $scope->key;
            $scopeType = $thread?->scope_type ?? $phoneNumber?->scope_type ?? $scope->type;
            $scopeId = $thread?->scope_id ?? $phoneNumber?->scope_id ?? $scope->id;

            return PhoneMessage::query()->create([
                'scope_key' => $scopeKey,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'provider' => 'twilio',
                'phone_thread_id' => $thread?->getKey(),
                'phone_number_id' => $phoneNumber?->getKey() ?? $thread?->phone_number_id,
                'direction' => 'outbound',
                'from_number' => $from,
                'to_number' => $message->to,
                'body' => $message->body,
                'media' => $message->mediaUrls,
                'status' => MessageStatus::QUEUED,
                'status_rank' => MessageStatus::rank(MessageStatus::QUEUED),
                'queued_at' => $queuedAt,
                'metadata' => $this->messageMetadata($message, $messagingServiceSid, $statusCallbackUrl),
            ]);
        });

        $this->events->dispatch(new OutboundMessageQueued($record));

        return $record;
    }

    private function validate(OutboundMessage $message): void
    {
        if ($message->to === '') {
            throw PhoneMessageException::missingRecipient();
        }

        if (($message->body === null || $message->body === '') && $message->mediaUrls === []) {
            throw PhoneMessageException::missingBodyAndMedia();
        }
    }

    private function resolveLocalNumber(?string $from, string $scopeKey, ?string $scopeType, ?string $scopeId): ?PhoneNumber
    {
        if ($from === null || $from === '') {
            return null;
        }

        /** @var PhoneNumber|null $existing */
        $existing = PhoneNumber::query()
            ->where('scope_key', $scopeKey)
            ->where('phone_number', $from)
            ->first();

        if ($existing instanceof PhoneNumber) {
            return $existing;
        }

        return PhoneNumber::query()->create([
            'scope_key' => $scopeKey,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'provider' => 'twilio',
            'phone_number' => $from,
            'capabilities' => [
                'sms' => true,
                'mms' => true,
                'voice' => true,
            ],
            'status' => 'active',
        ]);
    }

    private function findThread(PhoneNumber $phoneNumber, string $remoteNumber): ?PhoneThread
    {
        /** @var PhoneThread|null $thread */
        $thread = PhoneThread::query()
            ->where('scope_key', $phoneNumber->scope_key)
            ->where('phone_number_id', $phoneNumber->getKey())
            ->where('remote_number', $remoteNumber)
            ->first();

        return $thread;
    }

    private function findThreadForScope(string $scopeKey, string $remoteNumber): ?PhoneThread
    {
        /** @var PhoneThread|null $optedOutThread */
        $optedOutThread = PhoneThread::query()
            ->where('scope_key', $scopeKey)
            ->where('remote_number', $remoteNumber)
            ->whereNotNull('opted_out_at')
            ->first();

        if ($optedOutThread instanceof PhoneThread) {
            return $optedOutThread;
        }

        $threads = PhoneThread::query()
            ->where('scope_key', $scopeKey)
            ->where('remote_number', $remoteNumber)
            ->limit(2)
            ->get();

        if ($threads->count() !== 1) {
            return null;
        }

        /** @var PhoneThread $thread */
        $thread = $threads->first();

        return $thread;
    }

    private function createThread(PhoneNumber $phoneNumber, string $remoteNumber): PhoneThread
    {
        /** @var PhoneThread $thread */
        $thread = PhoneThread::query()->create([
            'scope_key' => $phoneNumber->scope_key,
            'phone_number_id' => $phoneNumber->getKey(),
            'remote_number' => $remoteNumber,
            'scope_type' => $phoneNumber->scope_type,
            'scope_id' => $phoneNumber->scope_id,
            'provider' => 'twilio',
            'local_number' => $phoneNumber->phone_number,
            'metadata' => [],
        ]);

        return $thread;
    }

    private function touchThreadForOutbound(PhoneThread $thread, DateTimeInterface $queuedAt): void
    {
        PhoneThread::query()
            ->whereKey($thread->getKey())
            ->update([
                'last_message_at' => $queuedAt,
                'last_outbound_message_at' => $queuedAt,
                'updated_at' => $queuedAt,
            ]);
    }

    private function applyThreadContact(PhoneThread $thread, ContactIdentity $contact): void
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

    /** @return array<string, mixed> */
    private function messageMetadata(OutboundMessage $message, ?string $messagingServiceSid, ?string $statusCallbackUrl): array
    {
        $metadata = array_replace_recursive($message->metadata, [
            'outbound' => [
                'messaging_service_sid' => $messagingServiceSid,
                'status_callback_url' => $statusCallbackUrl,
            ],
        ]);

        if ($message->contact instanceof ContactIdentity) {
            $metadata = array_replace_recursive($metadata, [
                'contact' => $message->contact->toArray(),
            ]);
        }

        return $metadata;
    }

    private function defaultStatusCallbackUrl(): ?string
    {
        $baseUrl = $this->stringConfig('phone.webhooks.base_url');

        if ($baseUrl === null) {
            return null;
        }

        return rtrim($baseUrl, '/').'/'.trim((string) $this->config->get('phone.route_prefix', 'phone'), '/').'/twilio/sms/status';
    }

    private function stringConfig(string $key): ?string
    {
        $value = $this->config->get($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
