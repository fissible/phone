<?php

declare(strict_types=1);

namespace Fissible\Phone\Voice;

use Fissible\Phone\Events\TranscriptionStatusUpdated;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneRecording;
use Fissible\Phone\Models\PhoneTranscription;
use Fissible\Phone\Models\PhoneVoicemail;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\TranscriptionStatus;
use Fissible\Phone\Twilio\TwilioTranscriptionPayload;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TranscriptionProcessor
{
    public function __construct(
        private readonly Repository $config,
        private readonly Dispatcher $events,
    ) {}

    public function processTwilio(Request $request, ?WebhookReceipt $receipt = null): PhoneTranscription
    {
        $payload = TwilioTranscriptionPayload::fromRequest($request);
        $recording = $this->resolveRecording($payload);
        $call = $this->resolveCall($request, $payload, $recording);
        $voicemail = $this->resolveVoicemail($recording);
        $oldStatus = null;
        $statusChanged = false;

        /** @var PhoneTranscription $transcription */
        $transcription = DB::transaction(function () use ($payload, $recording, $call, &$voicemail, $receipt, &$oldStatus, &$statusChanged): PhoneTranscription {
            /** @var PhoneTranscription|null $transcription */
            $transcription = PhoneTranscription::query()
                ->where('provider', 'twilio')
                ->where('provider_transcription_sid', $payload->transcriptionSid)
                ->lockForUpdate()
                ->first();

            if (! $transcription instanceof PhoneTranscription) {
                $statusChanged = true;
                $transcription = PhoneTranscription::query()->create(
                    $this->transcriptionAttributes($payload, $recording, $call, $voicemail, $receipt)
                );
            } else {
                $oldStatus = $transcription->status;
                $rank = TranscriptionStatus::rank($payload->status);

                if (! TranscriptionStatus::isTerminal($transcription->status) && $transcription->status_rank < $rank) {
                    $statusChanged = true;
                    $transcription->forceFill(
                        $this->transcriptionUpdates($transcription, $payload, $recording, $call, $voicemail, $receipt)
                    )->save();
                }
            }

            $transcription->refresh();
            $voicemail = $this->updateVoicemail($transcription, $voicemail, $receipt);

            return $transcription;
        });

        if ($statusChanged) {
            $this->events->dispatch(new TranscriptionStatusUpdated(
                transcription: $transcription,
                oldStatus: $oldStatus,
                newStatus: $transcription->status,
                voicemail: $voicemail,
                webhookReceipt: $receipt,
            ));
        }

        return $transcription;
    }

    private function resolveRecording(TwilioTranscriptionPayload $payload): ?PhoneRecording
    {
        if ($payload->recordingSid === null || $payload->recordingSid === '') {
            return null;
        }

        $recording = PhoneRecording::query()
            ->where('provider', 'twilio')
            ->where('provider_recording_sid', $payload->recordingSid)
            ->first();

        return $recording instanceof PhoneRecording ? $recording : null;
    }

    private function resolveCall(Request $request, TwilioTranscriptionPayload $payload, ?PhoneRecording $recording): ?PhoneCall
    {
        if ($recording?->call instanceof PhoneCall) {
            return $recording->call;
        }

        $callId = $this->nullableInteger($request, 'call_id');

        if ($callId !== null) {
            $call = PhoneCall::query()->find($callId);

            if ($call instanceof PhoneCall) {
                return $call;
            }
        }

        if ($payload->callSid === null || $payload->callSid === '') {
            return null;
        }

        $call = PhoneCall::query()
            ->where('provider', 'twilio')
            ->where('provider_call_sid', $payload->callSid)
            ->first();

        return $call instanceof PhoneCall ? $call : null;
    }

    private function resolveVoicemail(?PhoneRecording $recording): ?PhoneVoicemail
    {
        if (! $recording instanceof PhoneRecording) {
            return null;
        }

        $voicemail = $recording->voicemail;

        return $voicemail instanceof PhoneVoicemail ? $voicemail : null;
    }

    /** @return array<string, mixed> */
    private function transcriptionAttributes(
        TwilioTranscriptionPayload $payload,
        ?PhoneRecording $recording,
        ?PhoneCall $call,
        ?PhoneVoicemail $voicemail,
        ?WebhookReceipt $receipt,
    ): array {
        return $this->scopeAttributes($recording, $call, $voicemail) + [
            'provider' => 'twilio',
            'phone_call_id' => $call?->getKey(),
            'phone_recording_id' => $recording?->getKey(),
            'phone_voicemail_id' => $voicemail?->getKey(),
            'phone_number_id' => $recording?->phone_number_id ?? $call?->phone_number_id ?? $voicemail?->phone_number_id,
            'webhook_receipt_id' => $receipt?->getKey(),
            'provider_transcription_sid' => $payload->transcriptionSid,
            'provider_recording_sid' => $payload->recordingSid,
            'provider_call_sid' => $payload->callSid ?? $call?->provider_call_sid,
            'provider_account_sid' => $payload->accountSid ?? $recording?->provider_account_sid ?? $call?->provider_account_sid,
            'purpose' => $payload->purpose ?? $recording?->purpose,
            'status' => $payload->status,
            'status_rank' => TranscriptionStatus::rank($payload->status),
            'transcription_text' => $payload->transcriptionText,
            'transcription_url' => $payload->transcriptionUrl,
            'error_code' => $payload->errorCode,
            'error_message' => $payload->errorMessage,
            'metadata' => $payload->metadata(),
        ];
    }

    /** @return array<string, mixed> */
    private function transcriptionUpdates(
        PhoneTranscription $transcription,
        TwilioTranscriptionPayload $payload,
        ?PhoneRecording $recording,
        ?PhoneCall $call,
        ?PhoneVoicemail $voicemail,
        ?WebhookReceipt $receipt,
    ): array {
        return [
            'phone_call_id' => $transcription->phone_call_id ?? $call?->getKey(),
            'phone_recording_id' => $transcription->phone_recording_id ?? $recording?->getKey(),
            'phone_voicemail_id' => $transcription->phone_voicemail_id ?? $voicemail?->getKey(),
            'phone_number_id' => $transcription->phone_number_id
                ?? $recording?->phone_number_id
                ?? $call?->phone_number_id
                ?? $voicemail?->phone_number_id,
            'webhook_receipt_id' => $receipt?->getKey() ?? $transcription->webhook_receipt_id,
            'provider_recording_sid' => $payload->recordingSid ?? $transcription->provider_recording_sid,
            'provider_call_sid' => $payload->callSid ?? $call?->provider_call_sid ?? $transcription->provider_call_sid,
            'provider_account_sid' => $payload->accountSid
                ?? $recording?->provider_account_sid
                ?? $call?->provider_account_sid
                ?? $transcription->provider_account_sid,
            'purpose' => $payload->purpose ?? $recording?->purpose ?? $transcription->purpose,
            'status' => $payload->status,
            'status_rank' => TranscriptionStatus::rank($payload->status),
            'transcription_text' => $payload->transcriptionText ?? $transcription->transcription_text,
            'transcription_url' => $payload->transcriptionUrl ?? $transcription->transcription_url,
            'error_code' => $payload->errorCode,
            'error_message' => $payload->errorMessage,
            'metadata' => array_replace($transcription->metadata ?? [], $payload->metadata()),
        ];
    }

    private function updateVoicemail(
        PhoneTranscription $transcription,
        ?PhoneVoicemail $voicemail,
        ?WebhookReceipt $receipt,
    ): ?PhoneVoicemail {
        if (! $voicemail instanceof PhoneVoicemail || $transcription->status !== TranscriptionStatus::COMPLETED) {
            return $voicemail;
        }

        $voicemail->forceFill([
            'webhook_receipt_id' => $receipt?->getKey() ?? $voicemail->webhook_receipt_id,
            'status' => 'transcribed',
            'transcription_text' => $transcription->transcription_text,
            'metadata' => array_replace($voicemail->metadata ?? [], [
                'phone_transcription_id' => $transcription->getKey(),
                'provider_transcription_sid' => $transcription->provider_transcription_sid,
            ]),
        ])->save();

        return $voicemail->refresh();
    }

    /** @return array<string, mixed> */
    private function scopeAttributes(?PhoneRecording $recording, ?PhoneCall $call, ?PhoneVoicemail $voicemail): array
    {
        return [
            'scope_key' => $recording?->scope_key
                ?? $call?->scope_key
                ?? $voicemail?->scope_key
                ?? (string) $this->config->get('phone.numbers.default_scope_key', 'global'),
            'scope_type' => $recording?->scope_type
                ?? $call?->scope_type
                ?? $voicemail?->scope_type
                ?? $this->nullableString($this->config->get('phone.numbers.default_scope_type')),
            'scope_id' => $recording?->scope_id
                ?? $call?->scope_id
                ?? $voicemail?->scope_id
                ?? $this->nullableString($this->config->get('phone.numbers.default_scope_id')),
        ];
    }

    private function nullableInteger(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
