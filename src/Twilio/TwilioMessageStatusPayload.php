<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Exceptions\PhoneWebhookException;
use Fissible\Phone\Support\MessageStatus;
use Illuminate\Http\Request;

class TwilioMessageStatusPayload
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $messageSid,
        public readonly ?string $accountSid,
        public readonly string $providerStatus,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly array $raw,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            messageSid: self::requiredString($request, 'MessageSid', 'SmsSid'),
            accountSid: self::nullableString($request, 'AccountSid'),
            providerStatus: strtolower(self::requiredString($request, 'MessageStatus', 'SmsStatus')),
            from: self::nullableString($request, 'From'),
            to: self::nullableString($request, 'To'),
            errorCode: self::nullableString($request, 'ErrorCode'),
            errorMessage: self::nullableString($request, 'ErrorMessage'),
            raw: $request->request->all(),
        );
    }

    public function internalStatus(): ?string
    {
        return match ($this->providerStatus) {
            'accepted', 'scheduled', 'queued' => MessageStatus::QUEUED,
            'sending' => MessageStatus::SENDING,
            'sent' => MessageStatus::SENT,
            'delivered', 'read' => MessageStatus::DELIVERED,
            'undelivered' => MessageStatus::UNDELIVERED,
            'failed', 'canceled' => MessageStatus::FAILED,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return [
            'twilio_status_callback' => array_filter([
                'message_sid' => $this->messageSid,
                'account_sid' => $this->accountSid,
                'provider_status' => $this->providerStatus,
                'from' => $this->from,
                'to' => $this->to,
                'error_code' => $this->errorCode,
                'error_message' => $this->errorMessage,
                'raw' => $this->raw,
            ], static fn ($value): bool => $value !== null),
        ];
    }

    private static function requiredString(Request $request, string ...$keys): string
    {
        foreach ($keys as $key) {
            $value = self::nullableString($request, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        throw PhoneWebhookException::missingField(implode('|', $keys));
    }

    private static function nullableString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }
}
