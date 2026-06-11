<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Exceptions\PhoneWebhookException;
use Fissible\Phone\Support\TranscriptionStatus;
use Illuminate\Http\Request;

class TwilioTranscriptionPayload
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $transcriptionSid,
        public readonly ?string $recordingSid,
        public readonly ?string $callSid,
        public readonly ?string $accountSid,
        public readonly string $status,
        public readonly ?string $transcriptionText,
        public readonly ?string $transcriptionUrl,
        public readonly ?string $recordingUrl,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly ?string $purpose,
        public readonly array $raw,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            transcriptionSid: self::requiredString($request, 'TranscriptionSid'),
            recordingSid: self::nullableString($request, 'RecordingSid'),
            callSid: self::nullableString($request, 'CallSid'),
            accountSid: self::nullableString($request, 'AccountSid'),
            status: self::normalizeStatus(self::nullableString($request, 'TranscriptionStatus')),
            transcriptionText: self::nullableString($request, 'TranscriptionText'),
            transcriptionUrl: self::nullableString($request, 'TranscriptionUrl'),
            recordingUrl: self::nullableString($request, 'RecordingUrl'),
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
            'twilio_transcription_callback' => array_filter([
                'transcription_sid' => $this->transcriptionSid,
                'recording_sid' => $this->recordingSid,
                'call_sid' => $this->callSid,
                'account_sid' => $this->accountSid,
                'status' => $this->status,
                'transcription_url' => $this->transcriptionUrl,
                'recording_url' => $this->recordingUrl,
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
            'in_progress', 'processing' => TranscriptionStatus::IN_PROGRESS,
            'completed' => TranscriptionStatus::COMPLETED,
            'failed' => TranscriptionStatus::FAILED,
            default => TranscriptionStatus::COMPLETED,
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
}
