<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Support\CallStatus;
use Illuminate\Http\Request;

class TwilioCallStatusPayload
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly ?string $callSid,
        public readonly ?string $parentCallSid,
        public readonly ?string $accountSid,
        public readonly ?string $providerStatus,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?string $direction,
        public readonly ?int $sequenceNumber,
        public readonly ?int $durationSeconds,
        public readonly ?string $answeredBy,
        public readonly ?string $dialCallSid,
        public readonly ?string $dialCallStatus,
        public readonly ?int $dialCallDuration,
        public readonly ?bool $dialBridged,
        public readonly array $raw,
    ) {}

    public static function fromStatusRequest(Request $request): self
    {
        return new self(
            callSid: self::nullableString($request, 'CallSid'),
            parentCallSid: self::nullableString($request, 'ParentCallSid'),
            accountSid: self::nullableString($request, 'AccountSid'),
            providerStatus: self::nullableString($request, 'CallStatus'),
            from: self::nullableString($request, 'From', 'Caller'),
            to: self::nullableString($request, 'To', 'Called'),
            direction: self::nullableString($request, 'Direction'),
            sequenceNumber: self::nullableInteger($request, 'SequenceNumber'),
            durationSeconds: self::nullableInteger($request, 'CallDuration'),
            answeredBy: self::nullableString($request, 'AnsweredBy'),
            dialCallSid: null,
            dialCallStatus: null,
            dialCallDuration: null,
            dialBridged: null,
            raw: $request->request->all(),
        );
    }

    public static function fromDialStatusRequest(Request $request): self
    {
        return new self(
            callSid: self::nullableString($request, 'CallSid'),
            parentCallSid: self::nullableString($request, 'ParentCallSid'),
            accountSid: self::nullableString($request, 'AccountSid'),
            providerStatus: self::nullableString($request, 'CallStatus'),
            from: self::nullableString($request, 'From', 'Caller'),
            to: self::nullableString($request, 'To', 'Called'),
            direction: self::nullableString($request, 'Direction'),
            sequenceNumber: self::nullableInteger($request, 'SequenceNumber'),
            durationSeconds: self::nullableInteger($request, 'CallDuration'),
            answeredBy: self::nullableString($request, 'AnsweredBy'),
            dialCallSid: self::nullableString($request, 'DialCallSid'),
            dialCallStatus: self::nullableString($request, 'DialCallStatus'),
            dialCallDuration: self::nullableInteger($request, 'DialCallDuration'),
            dialBridged: self::nullableBoolean($request, 'DialBridged'),
            raw: $request->request->all(),
        );
    }

    public function internalStatus(bool $dialStatus = false): ?string
    {
        $status = $dialStatus
            ? $this->normalizeStatus($this->dialCallStatus)
            : $this->normalizeStatus($this->providerStatus);

        return $status === '' ? null : $status;
    }

    /** @return array<string, mixed> */
    public function metadata(string $source): array
    {
        return [
            $source => array_filter([
                'call_sid' => $this->callSid,
                'parent_call_sid' => $this->parentCallSid,
                'account_sid' => $this->accountSid,
                'provider_status' => $this->providerStatus,
                'from' => $this->from,
                'to' => $this->to,
                'direction' => $this->direction,
                'sequence_number' => $this->sequenceNumber,
                'duration_seconds' => $this->durationSeconds,
                'answered_by' => $this->answeredBy,
                'dial_call_sid' => $this->dialCallSid,
                'dial_call_status' => $this->dialCallStatus,
                'dial_call_duration' => $this->dialCallDuration,
                'dial_bridged' => $this->dialBridged,
                'raw' => $this->raw,
            ], static fn ($value): bool => $value !== null),
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        $status = strtolower(str_replace('-', '_', trim((string) $status)));

        return match ($status) {
            'initiated' => CallStatus::INITIATED,
            'ringing' => CallStatus::RINGING,
            'in_progress' => CallStatus::IN_PROGRESS,
            'completed' => CallStatus::COMPLETED,
            'busy' => CallStatus::BUSY,
            'failed' => CallStatus::FAILED,
            'no_answer' => CallStatus::NO_ANSWER,
            'canceled', 'cancelled' => CallStatus::CANCELED,
            default => '',
        };
    }

    private static function nullableString(Request $request, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->input($key);

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private static function nullableInteger(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function nullableBoolean(Request $request, string $key): ?bool
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
