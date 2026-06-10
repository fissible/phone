<?php

declare(strict_types=1);

namespace Fissible\Phone\Sms;

use DateTimeInterface;
use Fissible\Phone\Contracts\ScopeResolver;
use Fissible\Phone\Events\OutboundMessageQueued;
use Fissible\Phone\Exceptions\PhoneMessageException;
use Fissible\Phone\Jobs\SendOutboundMessage;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Support\MessageStatus;
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
                ? $this->resolveThread($phoneNumber, $message->to, $queuedAt)
                : null;

            $scopeKey = $phoneNumber?->scope_key ?? $scope->key;
            $scopeType = $phoneNumber?->scope_type ?? $scope->type;
            $scopeId = $phoneNumber?->scope_id ?? $scope->id;

            return PhoneMessage::query()->create([
                'scope_key' => $scopeKey,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'provider' => 'twilio',
                'phone_thread_id' => $thread?->getKey(),
                'phone_number_id' => $phoneNumber?->getKey(),
                'direction' => 'outbound',
                'from_number' => $from,
                'to_number' => $message->to,
                'body' => $message->body,
                'media' => $message->mediaUrls,
                'status' => MessageStatus::QUEUED,
                'status_rank' => MessageStatus::rank(MessageStatus::QUEUED),
                'queued_at' => $queuedAt,
                'metadata' => array_replace_recursive($message->metadata, [
                    'outbound' => [
                        'messaging_service_sid' => $messagingServiceSid,
                        'status_callback_url' => $statusCallbackUrl,
                    ],
                ]),
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

    private function resolveThread(PhoneNumber $phoneNumber, string $remoteNumber, DateTimeInterface $queuedAt): PhoneThread
    {
        /** @var PhoneThread $thread */
        $thread = PhoneThread::query()->firstOrCreate([
            'scope_key' => $phoneNumber->scope_key,
            'phone_number_id' => $phoneNumber->getKey(),
            'remote_number' => $remoteNumber,
        ], [
            'scope_type' => $phoneNumber->scope_type,
            'scope_id' => $phoneNumber->scope_id,
            'provider' => 'twilio',
            'local_number' => $phoneNumber->phone_number,
            'metadata' => [],
        ]);

        PhoneThread::query()
            ->whereKey($thread->getKey())
            ->update([
                'last_message_at' => $queuedAt,
                'last_outbound_message_at' => $queuedAt,
                'updated_at' => $queuedAt,
            ]);

        return $thread;
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
