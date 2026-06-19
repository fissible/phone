<?php

declare(strict_types=1);

namespace Fissible\Phone\Calls;

use Fissible\Phone\Contracts\ScopeResolver;
use Fissible\Phone\Events\OutboundCallQueued;
use Fissible\Phone\Exceptions\PhoneCallException;
use Fissible\Phone\Jobs\SendOutboundCall;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\OutboundCall;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\DB;

class OutboundCallService
{
    public function __construct(
        private readonly Repository $config,
        private readonly ScopeResolver $scopeResolver,
        private readonly Dispatcher $bus,
        private readonly EventDispatcher $events,
    ) {}

    public function send(OutboundCall $call): PhoneCall
    {
        $record = $this->create($call);

        $this->bus->dispatchSync(new SendOutboundCall((int) $record->getKey()));

        return $record->refresh();
    }

    public function queue(OutboundCall $call): PhoneCall
    {
        $record = $this->create($call);

        $this->bus->dispatch(new SendOutboundCall((int) $record->getKey()));

        return $record->refresh();
    }

    public function create(OutboundCall $call): PhoneCall
    {
        $this->validate($call);

        $scope = $this->scopeResolver->resolve();
        $from = $call->from ?: $this->stringConfig('phone.twilio.default_from');
        $statusCallbackUrl = $call->statusCallbackUrl ?: $this->defaultStatusCallbackUrl();

        /** @var PhoneCall $record */
        $record = DB::transaction(function () use ($call, $scope, $from, $statusCallbackUrl): PhoneCall {
            $phoneNumber = $this->resolveLocalNumber($from, $scope->key, $scope->type, $scope->id);

            $scopeKey = $phoneNumber?->scope_key ?? $scope->key;
            $scopeType = $phoneNumber?->scope_type ?? $scope->type;
            $scopeId = $phoneNumber?->scope_id ?? $scope->id;

            return PhoneCall::query()->create([
                'scope_key' => $scopeKey,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'provider' => 'twilio',
                'phone_number_id' => $phoneNumber?->getKey(),
                'direction' => 'outbound',
                'from_number' => (string) $from,
                'to_number' => $call->to,
                'status' => CallStatus::QUEUED,
                'status_rank' => CallStatus::rank(CallStatus::QUEUED),
                'metadata' => $this->callMetadata($call, $statusCallbackUrl),
            ]);
        });

        $this->events->dispatch(new OutboundCallQueued($record));

        return $record;
    }

    private function validate(OutboundCall $call): void
    {
        if ($call->to === '') {
            throw PhoneCallException::missingRecipient();
        }

        if (($call->twiml === null || $call->twiml === '') && ($call->url === null || $call->url === '')) {
            throw PhoneCallException::missingInstructions();
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

    /** @return array<string, mixed> */
    private function callMetadata(OutboundCall $call, ?string $statusCallbackUrl): array
    {
        $metadata = array_replace_recursive($call->metadata, [
            'outbound' => [
                'twiml' => $call->twiml,
                'url' => $call->url,
                'status_callback_url' => $statusCallbackUrl,
                'machine_detection' => $call->machineDetection,
                'timeout' => $call->timeout,
            ],
        ]);

        if ($call->contact instanceof ContactIdentity) {
            $metadata = array_replace_recursive($metadata, [
                'contact' => $call->contact->toArray(),
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

        return rtrim($baseUrl, '/').'/'.trim((string) $this->config->get('phone.route_prefix', 'phone'), '/').'/twilio/voice/status';
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
