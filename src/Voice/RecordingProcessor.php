<?php

declare(strict_types=1);

namespace Fissible\Phone\Voice;

use Fissible\Phone\Events\RecordingStatusUpdated;
use Fissible\Phone\Events\VoicemailReceived;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneRecording;
use Fissible\Phone\Models\PhoneVoicemail;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\RecordingStatus;
use Fissible\Phone\Twilio\TwilioRecordingPayload;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordingProcessor
{
    public function __construct(
        private readonly Repository $config,
        private readonly Dispatcher $events,
    ) {}

    public function processTwilio(Request $request, ?WebhookReceipt $receipt = null): PhoneRecording
    {
        $payload = TwilioRecordingPayload::fromRequest($request);
        $call = $this->resolveCall($request, $payload);
        $oldStatus = null;
        $statusChanged = false;
        $voicemailCreated = null;

        /** @var PhoneRecording $recording */
        $recording = DB::transaction(function () use ($payload, $call, $receipt, &$oldStatus, &$statusChanged, &$voicemailCreated): PhoneRecording {
            /** @var PhoneRecording|null $recording */
            $recording = PhoneRecording::query()
                ->where('provider', 'twilio')
                ->where('provider_recording_sid', $payload->recordingSid)
                ->lockForUpdate()
                ->first();

            if (! $recording instanceof PhoneRecording) {
                $statusChanged = true;
                $recording = PhoneRecording::query()->create($this->recordingAttributes($payload, $call, $receipt));
            } else {
                $oldStatus = $recording->status;
                $rank = RecordingStatus::rank($payload->status);

                if (! RecordingStatus::isTerminal($recording->status) && $recording->status_rank < $rank) {
                    $statusChanged = true;
                    $recording->forceFill($this->recordingUpdates($recording, $payload, $call, $receipt))->save();
                }
            }

            $recording->refresh();
            $voicemailCreated = $this->createVoicemail($recording, $call, $receipt);

            return $recording;
        });

        if ($statusChanged) {
            $this->events->dispatch(new RecordingStatusUpdated(
                recording: $recording,
                oldStatus: $oldStatus,
                newStatus: $recording->status,
                webhookReceipt: $receipt,
            ));
        }

        if ($voicemailCreated instanceof PhoneVoicemail) {
            $this->events->dispatch(new VoicemailReceived(
                voicemail: $voicemailCreated,
                recording: $recording,
                call: $call,
                webhookReceipt: $receipt,
            ));
        }

        return $recording;
    }

    private function resolveCall(Request $request, TwilioRecordingPayload $payload): ?PhoneCall
    {
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

    /** @return array<string, mixed> */
    private function recordingAttributes(TwilioRecordingPayload $payload, ?PhoneCall $call, ?WebhookReceipt $receipt): array
    {
        return $this->scopeAttributes($call) + [
            'provider' => 'twilio',
            'phone_call_id' => $call?->getKey(),
            'phone_number_id' => $call?->phone_number_id,
            'webhook_receipt_id' => $receipt?->getKey(),
            'provider_recording_sid' => $payload->recordingSid,
            'provider_call_sid' => $payload->callSid,
            'provider_account_sid' => $payload->accountSid,
            'purpose' => $payload->purpose,
            'status' => $payload->status,
            'status_rank' => RecordingStatus::rank($payload->status),
            'recording_url' => $payload->recordingUrl,
            'duration_seconds' => $payload->durationSeconds,
            'channels' => $payload->channels,
            'source' => $payload->source,
            'track' => $payload->track,
            'error_code' => $payload->errorCode,
            'error_message' => $payload->errorMessage,
            'metadata' => $payload->metadata(),
        ];
    }

    /** @return array<string, mixed> */
    private function recordingUpdates(
        PhoneRecording $recording,
        TwilioRecordingPayload $payload,
        ?PhoneCall $call,
        ?WebhookReceipt $receipt,
    ): array {
        return [
            'phone_call_id' => $recording->phone_call_id ?? $call?->getKey(),
            'phone_number_id' => $recording->phone_number_id ?? $call?->phone_number_id,
            'webhook_receipt_id' => $receipt?->getKey() ?? $recording->webhook_receipt_id,
            'provider_call_sid' => $payload->callSid ?? $recording->provider_call_sid,
            'provider_account_sid' => $payload->accountSid ?? $recording->provider_account_sid,
            'purpose' => $payload->purpose ?? $recording->purpose,
            'status' => $payload->status,
            'status_rank' => RecordingStatus::rank($payload->status),
            'recording_url' => $payload->recordingUrl ?? $recording->recording_url,
            'duration_seconds' => $payload->durationSeconds ?? $recording->duration_seconds,
            'channels' => $payload->channels ?? $recording->channels,
            'source' => $payload->source ?? $recording->source,
            'track' => $payload->track ?? $recording->track,
            'error_code' => $payload->errorCode,
            'error_message' => $payload->errorMessage,
            'metadata' => array_replace($recording->metadata ?? [], $payload->metadata()),
        ];
    }

    private function createVoicemail(PhoneRecording $recording, ?PhoneCall $call, ?WebhookReceipt $receipt): ?PhoneVoicemail
    {
        if ($recording->purpose !== 'voicemail' || $recording->status !== RecordingStatus::COMPLETED) {
            return null;
        }

        if ($recording->voicemail()->exists()) {
            return null;
        }

        return PhoneVoicemail::query()->create($this->scopeAttributes($call) + [
            'provider' => $recording->provider,
            'phone_call_id' => $recording->phone_call_id,
            'phone_recording_id' => $recording->getKey(),
            'phone_number_id' => $recording->phone_number_id,
            'webhook_receipt_id' => $receipt?->getKey() ?? $recording->webhook_receipt_id,
            'from_number' => $call?->from_number,
            'to_number' => $call?->to_number,
            'status' => 'received',
            'recording_url' => $recording->recording_url,
            'duration_seconds' => $recording->duration_seconds,
            'received_at' => now(),
            'metadata' => [
                'phone_recording_id' => $recording->getKey(),
                'provider_recording_sid' => $recording->provider_recording_sid,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function scopeAttributes(?PhoneCall $call): array
    {
        return [
            'scope_key' => $call?->scope_key ?? (string) $this->config->get('phone.numbers.default_scope_key', 'global'),
            'scope_type' => $call?->scope_type ?? $this->nullableString($this->config->get('phone.numbers.default_scope_type')),
            'scope_id' => $call?->scope_id ?? $this->nullableString($this->config->get('phone.numbers.default_scope_id')),
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
