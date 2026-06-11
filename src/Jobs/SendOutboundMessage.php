<?php

declare(strict_types=1);

namespace Fissible\Phone\Jobs;

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Events\OutboundMessageFailed;
use Fissible\Phone\Events\OutboundMessageSent;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\Exceptions\PhoneMessageException;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Support\MessageStatus;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class SendOutboundMessage implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(PhoneProvider $provider, Dispatcher $events): ?PhoneMessage
    {
        $claimed = PhoneMessage::query()
            ->whereKey($this->messageId)
            ->where('status', MessageStatus::QUEUED)
            ->whereNull('provider_message_sid')
            ->update([
                'status' => MessageStatus::SENDING,
                'status_rank' => MessageStatus::rank(MessageStatus::SENDING),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return PhoneMessage::query()->find($this->messageId);
        }

        /** @var PhoneMessage|null $message */
        $message = PhoneMessage::query()->find($this->messageId);

        if (! $message instanceof PhoneMessage
            || $message->provider_message_sid !== null
            || $message->status !== MessageStatus::SENDING) {
            return $message;
        }

        try {
            $receipt = $provider->sendMessage($this->outboundMessage($message));
        } catch (PhoneConfigurationException|PhoneMessageException $exception) {
            $this->markFailed($message, $exception);
            $message->refresh();
            $events->dispatch(new OutboundMessageFailed($message, $exception));

            throw $exception;
        } catch (Throwable $exception) {
            $this->markSendUnknown($message, $exception);
            $message->refresh();
            $events->dispatch(new OutboundMessageFailed($message, $exception));

            return $message;
        }

        $message->forceFill([
            'provider_message_sid' => $receipt->providerMessageSid,
            'status' => MessageStatus::SENT,
            'status_rank' => MessageStatus::rank(MessageStatus::SENT),
            'sent_at' => now(),
            'error_code' => null,
            'error_message' => null,
            'metadata' => array_replace_recursive($message->metadata ?? [], [
                'provider_response' => $receipt->raw,
            ]),
        ])->save();

        $message->refresh();
        $events->dispatch(new OutboundMessageSent($message));

        return $message;
    }

    private function outboundMessage(PhoneMessage $message): OutboundMessage
    {
        $metadata = $message->metadata ?? [];
        $outbound = is_array($metadata['outbound'] ?? null) ? $metadata['outbound'] : [];
        $media = is_array($message->media) ? array_values($message->media) : [];

        return new OutboundMessage(
            to: $message->to_number,
            from: $message->from_number,
            messagingServiceSid: $this->nullableString($outbound['messaging_service_sid'] ?? null),
            body: $message->body,
            mediaUrls: $media,
            statusCallbackUrl: $this->nullableString($outbound['status_callback_url'] ?? null),
            metadata: $metadata,
            contact: $this->contactFromMetadata($metadata),
        );
    }

    /** @param array<string, mixed> $metadata */
    private function contactFromMetadata(array $metadata): ?ContactIdentity
    {
        $contact = $metadata['contact'] ?? null;

        if (! is_array($contact)) {
            return null;
        }

        return ContactIdentity::fromArray($contact);
    }

    private function markFailed(PhoneMessage $message, Throwable $exception): void
    {
        $message->forceFill([
            'status' => MessageStatus::FAILED,
            'status_rank' => MessageStatus::rank(MessageStatus::FAILED),
            'failed_at' => now(),
            'error_code' => $exception::class,
            'error_message' => $exception->getMessage(),
        ])->save();
    }

    private function markSendUnknown(PhoneMessage $message, Throwable $exception): void
    {
        $message->forceFill([
            'status' => MessageStatus::SEND_UNKNOWN,
            'status_rank' => MessageStatus::rank(MessageStatus::SEND_UNKNOWN),
            'error_code' => $exception::class,
            'error_message' => $exception->getMessage(),
            'metadata' => array_replace_recursive($message->metadata ?? [], [
                'send_unknown' => [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]),
        ])->save();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
