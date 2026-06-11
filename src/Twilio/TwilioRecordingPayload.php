<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Exceptions\PhoneWebhookException;
use Fissible\Phone\Support\RecordingStatus;
use Illuminate\Http\Request;

class TwilioRecordingPayload
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $recordingSid,
        public readonly ?string $callSid,
        public readonly ?string $accountSid,
        public readonly string $status,
        public readonly ?string $recordingUrl,
        public readonly ?int $durationSeconds,
        public readonly ?int $channels,
        public readonly ?string $source,
        public readonly ?string $track,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly ?string $purpose,
        public readonly array $raw,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            recordingSid: self::requiredString($request, 'RecordingSid'),
            callSid: self::nullableString($request, 'CallSid'),
            accountSid: self::nullableString($request, 'AccountSid'),
            status: self::normalizeStatus(self::nullableString($request, 'RecordingStatus')),
            recordingUrl: self::nullableString($request, 'RecordingUrl'),
            durationSeconds: self::nullableInteger($request, 'RecordingDuration'),
            channels: self::nullableInteger($request, 'RecordingChannels'),
            source: self::nullableString($request, 'RecordingSource'),
            track: self::nullableString($request, 'RecordingTrack'),
            errorCode: self::nullableString($request, 'ErrorCode'),
            errorMessage: self::nullableString($request, 'ErrorMessage'),
            purpose: self::nullableString($request, 'purpose'),
            raw: $request->request->all(),
        );
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return [
            'twilio_recording_callback' => array_filter([
                'recording_sid' => $this->recordingSid,
                'call_sid' => $this->callSid,
                'account_sid' => $this->accountSid,
                'status' => $this->status,
                'recording_url' => $this->recordingUrl,
                'duration_seconds' => $this->durationSeconds,
                'channels' => $this->channels,
                'source' => $this->source,
                'track' => $this->track,
                'error_code' => $this->errorCode,
                'error_message' => $this->errorMessage,
                'purpose' => $this->purpose,
                'raw' => $this->raw,
            ], static fn ($value): bool => $value !== null),
        ];
    }

    private static function normalizeStatus(?string $status): string
    {
        $status = strtolower(str_replace('-', '_', (string) $status));

        return match ($status) {
            'in_progress' => RecordingStatus::IN_PROGRESS,
            'processing' => RecordingStatus::PROCESSING,
            'completed' => RecordingStatus::COMPLETED,
            'absent' => RecordingStatus::ABSENT,
            'failed' => RecordingStatus::FAILED,
            default => RecordingStatus::COMPLETED,
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

        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
