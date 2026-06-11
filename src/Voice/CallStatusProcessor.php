<?php

declare(strict_types=1);

namespace Fissible\Phone\Voice;

use Fissible\Phone\Contracts\TeamNotifier;
use Fissible\Phone\Events\CallStatusUpdated;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\Twilio\TwilioCallStatusPayload;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\TeamNotification;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class CallStatusProcessor
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly TeamNotifier $notifier,
    ) {}

    public function processTwilioStatus(Request $request, ?WebhookReceipt $receipt = null): ?PhoneCall
    {
        return $this->process(
            payload: TwilioCallStatusPayload::fromStatusRequest($request),
            request: $request,
            receipt: $receipt,
            source: 'twilio_status_callback',
            useDialStatus: false,
        );
    }

    public function processTwilioDialStatus(Request $request, ?WebhookReceipt $receipt = null): ?PhoneCall
    {
        return $this->process(
            payload: TwilioCallStatusPayload::fromDialStatusRequest($request),
            request: $request,
            receipt: $receipt,
            source: 'twilio_dial_status_callback',
            useDialStatus: true,
        );
    }

    private function process(
        TwilioCallStatusPayload $payload,
        Request $request,
        ?WebhookReceipt $receipt,
        string $source,
        bool $useDialStatus,
    ): ?PhoneCall {
        $status = $payload->internalStatus($useDialStatus);

        if ($status === null) {
            return null;
        }

        $call = $this->resolveCall($request, $payload);

        if (! $call instanceof PhoneCall) {
            return null;
        }

        $oldStatus = $call->status;
        $rank = CallStatus::rank($status);

        $updated = PhoneCall::query()
            ->whereKey($call->getKey())
            ->whereNotIn('status', CallStatus::terminalStatuses())
            ->where('status_rank', '<', $rank)
            ->update($this->updates($call, $payload, $status, $rank, $receipt, $source, $useDialStatus));

        $call->refresh();

        if ($updated === 1) {
            $this->events->dispatch(new CallStatusUpdated(
                call: $call,
                oldStatus: $oldStatus,
                newStatus: $status,
                providerStatus: $useDialStatus ? $payload->dialCallStatus : $payload->providerStatus,
                webhookReceipt: $receipt,
            ));

            if ($this->shouldNotifyMissedCall($call, $status)) {
                $this->notifyMissedCall($call, $payload, $receipt, $source, $useDialStatus);
            }
        }

        return $call;
    }

    private function shouldNotifyMissedCall(PhoneCall $call, string $status): bool
    {
        return $call->direction === 'inbound'
            && in_array($status, [
                CallStatus::BUSY,
                CallStatus::FAILED,
                CallStatus::NO_ANSWER,
                CallStatus::CANCELED,
                CallStatus::MISSED,
            ], true);
    }

    private function notifyMissedCall(
        PhoneCall $call,
        TwilioCallStatusPayload $payload,
        ?WebhookReceipt $receipt,
        string $source,
        bool $useDialStatus,
    ): void {
        $this->notifier->notify(new TeamNotification(
            type: 'voice.missed',
            channel: 'voice',
            occurredAt: $call->ended_at ?? now(),
            direction: 'inbound',
            phoneNumber: $call->phoneNumber()->first(),
            call: $call,
            contact: ContactIdentity::anonymous($call->from_number ?? 'Unknown'),
            webhookReceipt: $receipt,
            metadata: [
                'provider_call_sid' => $call->provider_call_sid,
                'provider_status' => $useDialStatus ? $payload->dialCallStatus : $payload->providerStatus,
                'source' => $source,
            ],
        ));
    }

    private function resolveCall(Request $request, TwilioCallStatusPayload $payload): ?PhoneCall
    {
        $callId = $this->nullableInteger($request, 'call_id');

        if ($callId !== null) {
            $call = PhoneCall::query()->find($callId);

            if ($call instanceof PhoneCall) {
                return $call;
            }
        }

        foreach ([$payload->callSid, $payload->dialCallSid] as $callSid) {
            if ($callSid === null || $callSid === '') {
                continue;
            }

            $call = PhoneCall::query()
                ->where('provider', 'twilio')
                ->where('provider_call_sid', $callSid)
                ->first();

            if ($call instanceof PhoneCall) {
                return $call;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function updates(
        PhoneCall $call,
        TwilioCallStatusPayload $payload,
        string $status,
        int $rank,
        ?WebhookReceipt $receipt,
        string $source,
        bool $useDialStatus,
    ): array {
        $now = now();
        $metadata = array_replace($call->metadata ?? [], $payload->metadata($source));
        $durationSeconds = $useDialStatus
            ? ($payload->dialCallDuration ?? $payload->durationSeconds)
            : $payload->durationSeconds;

        $updates = [
            'status' => $status,
            'status_rank' => $rank,
            'webhook_receipt_id' => $receipt?->getKey() ?? $call->webhook_receipt_id,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ];

        if ($payload->sequenceNumber !== null) {
            $updates['provider_sequence_number'] = $payload->sequenceNumber;
        }

        if ($payload->parentCallSid !== null) {
            $updates['provider_parent_call_sid'] = $payload->parentCallSid;
        }

        if ($payload->answeredBy !== null) {
            $updates['answered_by'] = $payload->answeredBy;
        }

        if ($durationSeconds !== null) {
            $updates['duration_seconds'] = $durationSeconds;
        }

        if ($status === CallStatus::IN_PROGRESS) {
            $updates['answered_at'] = $call->answered_at ?? $now;
        }

        if (CallStatus::isTerminal($status)) {
            $updates['ended_at'] = $call->ended_at ?? $now;
        }

        return $updates;
    }

    private function nullableInteger(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
