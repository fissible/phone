<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Sms\InboundSmsProcessor;
use Fissible\Phone\Sms\MessageStatusProcessor;
use Fissible\Phone\Voice\AiSessionStatusProcessor;
use Fissible\Phone\Voice\CallStatusProcessor;
use Fissible\Phone\Voice\InboundVoiceProcessor;
use Fissible\Phone\Voice\RecordingProcessor;
use Fissible\Phone\Voice\TranscriptionProcessor;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class WebhookReplayService
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function markForReplay(WebhookReceipt $receipt): WebhookReceipt
    {
        $receipt->forceFill([
            'processing_status' => 'pending',
            'failed_at' => null,
            'error_class' => null,
            'error_message' => null,
            'replay_count' => $receipt->replay_count + 1,
        ])->save();

        return $receipt->refresh();
    }

    /**
     * Reprocess a stored receipt by replaying its payload through the matching
     * processor, then record the outcome on the receipt.
     */
    public function replay(WebhookReceipt $receipt): WebhookReceipt
    {
        $request = $this->rebuildRequest($receipt);

        try {
            $this->dispatch($receipt, $request);
            $receipt->markProcessed();
        } catch (Throwable $exception) {
            $receipt->markFailed($exception);
            $receipt->forceFill(['replay_count' => $receipt->replay_count + 1])->save();

            throw $exception;
        }

        $receipt->forceFill(['replay_count' => $receipt->replay_count + 1])->save();

        return $receipt->refresh();
    }

    private function dispatch(WebhookReceipt $receipt, Request $request): void
    {
        match ($receipt->event_type) {
            'sms.inbound' => $this->container->make(InboundSmsProcessor::class)->processTwilio($request, $receipt),
            'sms.status' => $this->container->make(MessageStatusProcessor::class)->processTwilio($request, $receipt),
            'voice.inbound' => $this->container->make(InboundVoiceProcessor::class)->processTwilio($request, $receipt),
            'voice.dial_status' => $this->container->make(CallStatusProcessor::class)->processTwilioDialStatus($request, $receipt),
            'voice.status' => $this->container->make(CallStatusProcessor::class)->processTwilioStatus($request, $receipt),
            'voice.recording' => $this->container->make(RecordingProcessor::class)->processTwilio($request, $receipt),
            'voice.transcription' => $this->container->make(TranscriptionProcessor::class)->processTwilio($request, $receipt),
            'ai.status' => $this->container->make(AiSessionStatusProcessor::class)->processTwilio($request, $receipt),
            default => throw new RuntimeException("Cannot replay unsupported webhook event type [{$receipt->event_type}]."),
        };
    }

    private function rebuildRequest(WebhookReceipt $receipt): Request
    {
        $payload = is_array($receipt->payload) ? $receipt->payload : [];

        return Request::create(
            uri: (string) $receipt->request_url,
            method: $receipt->request_method ?: 'POST',
            parameters: $payload,
        );
    }
}
