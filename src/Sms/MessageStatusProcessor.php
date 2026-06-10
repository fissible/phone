<?php

declare(strict_types=1);

namespace Fissible\Phone\Sms;

use Fissible\Phone\Events\MessageDeliveryUpdated;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\MessageStatus;
use Fissible\Phone\Twilio\TwilioMessageStatusPayload;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class MessageStatusProcessor
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function processTwilio(Request $request, ?WebhookReceipt $receipt = null): ?PhoneMessage
    {
        $payload = TwilioMessageStatusPayload::fromRequest($request);
        $status = $payload->internalStatus();

        if ($status === null) {
            return null;
        }

        /** @var PhoneMessage|null $message */
        $message = PhoneMessage::query()
            ->where('provider', 'twilio')
            ->where('direction', 'outbound')
            ->where('provider_message_sid', $payload->messageSid)
            ->first();

        if (! $message instanceof PhoneMessage) {
            return null;
        }

        $oldStatus = $message->status;
        $rank = MessageStatus::rank($status);

        $updated = PhoneMessage::query()
            ->whereKey($message->getKey())
            ->whereNotIn('status', MessageStatus::terminalStatuses())
            ->where('status_rank', '<', $rank)
            ->update($this->updates($message, $payload, $status, $rank, $receipt));

        $message->refresh();

        if ($updated === 1) {
            $this->events->dispatch(new MessageDeliveryUpdated(
                message: $message,
                oldStatus: $oldStatus,
                newStatus: $status,
                providerStatus: $payload->providerStatus,
                webhookReceipt: $receipt,
            ));
        }

        return $message;
    }

    /** @return array<string, mixed> */
    private function updates(
        PhoneMessage $message,
        TwilioMessageStatusPayload $payload,
        string $status,
        int $rank,
        ?WebhookReceipt $receipt,
    ): array {
        $now = now();
        $metadata = array_replace($message->metadata ?? [], $payload->metadata());

        $updates = [
            'status' => $status,
            'status_rank' => $rank,
            'webhook_receipt_id' => $receipt?->getKey() ?? $message->webhook_receipt_id,
            'error_code' => $payload->errorCode,
            'error_message' => $payload->errorMessage,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ];

        if ($status === MessageStatus::SENT) {
            $updates['sent_at'] = $message->sent_at ?? $now;
        }

        if ($status === MessageStatus::DELIVERED) {
            $updates['sent_at'] = $message->sent_at ?? $now;
            $updates['delivered_at'] = $now;
            $updates['error_code'] = null;
            $updates['error_message'] = null;
        }

        if (in_array($status, [MessageStatus::FAILED, MessageStatus::UNDELIVERED], true)) {
            $updates['failed_at'] = $now;
        }

        return $updates;
    }
}
