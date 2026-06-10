<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Exceptions\PhoneWebhookException;
use Fissible\Phone\Support\CallStatus;
use Illuminate\Http\Request;

class TwilioInboundVoicePayload
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $callSid,
        public readonly ?string $parentCallSid,
        public readonly ?string $accountSid,
        public readonly string $from,
        public readonly string $to,
        public readonly string $callStatus,
        public readonly ?string $direction,
        public readonly ?int $sequenceNumber,
        public readonly array $raw,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            callSid: self::requiredString($request, 'CallSid'),
            parentCallSid: self::nullableString($request, 'ParentCallSid'),
            accountSid: self::nullableString($request, 'AccountSid'),
            from: self::requiredString($request, 'From', 'Caller'),
            to: self::requiredString($request, 'To', 'Called'),
            callStatus: self::normalizeStatus(self::nullableString($request, 'CallStatus')),
            direction: self::nullableString($request, 'Direction'),
            sequenceNumber: self::nullableInteger($request, 'SequenceNumber'),
            raw: $request->request->all(),
        );
    }

    private static function normalizeStatus(?string $status): string
    {
        $status = strtolower(str_replace('-', '_', (string) $status));

        return match ($status) {
            'initiated' => CallStatus::INITIATED,
            'ringing' => CallStatus::RINGING,
            'in_progress' => CallStatus::IN_PROGRESS,
            'completed' => CallStatus::COMPLETED,
            'busy' => CallStatus::BUSY,
            'failed' => CallStatus::FAILED,
            'no_answer' => CallStatus::NO_ANSWER,
            'canceled', 'cancelled' => CallStatus::CANCELED,
            default => CallStatus::RINGING,
        };
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

    private static function nullableInteger(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
