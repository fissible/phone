<?php

declare(strict_types=1);

namespace Fissible\Phone\Http\Controllers;

use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Services\WebhookReceiptRecorder;
use Fissible\Phone\Sms\InboundSmsProcessor;
use Fissible\Phone\Sms\MessageStatusProcessor;
use Fissible\Phone\Voice\CallStatusProcessor;
use Fissible\Phone\Voice\InboundVoiceProcessor;
use Fissible\Phone\Voice\RecordingProcessor;
use Fissible\Phone\Voice\TranscriptionProcessor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TwilioWebhookController
{
    public function inboundSms(Request $request, WebhookReceiptRecorder $receipts, InboundSmsProcessor $processor): Response
    {
        try {
            $processor->processTwilio($request, $this->receipt($request));
            $receipts->markProcessed($this->receipt($request));

            return response()->noContent();
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    public function smsStatus(Request $request, WebhookReceiptRecorder $receipts, MessageStatusProcessor $processor): Response
    {
        try {
            $processor->processTwilio($request, $this->receipt($request));
            $receipts->markProcessed($this->receipt($request));

            return response()->noContent();
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    public function inboundVoice(Request $request, WebhookReceiptRecorder $receipts, InboundVoiceProcessor $processor): Response
    {
        try {
            $result = $processor->processTwilio($request, $this->receipt($request));
            $receipts->markProcessed($this->receipt($request));

            return response($result->twiml, Response::HTTP_OK, [
                'Content-Type' => 'text/xml',
            ]);
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    public function dialStatus(Request $request, WebhookReceiptRecorder $receipts, CallStatusProcessor $processor): Response
    {
        try {
            $processor->processTwilioDialStatus($request, $this->receipt($request));
            $receipts->markProcessed($this->receipt($request));

            return response($this->emptyTwiml(), Response::HTTP_OK, [
                'Content-Type' => 'text/xml',
            ]);
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    public function voiceStatus(Request $request, WebhookReceiptRecorder $receipts, CallStatusProcessor $processor): Response
    {
        try {
            $processor->processTwilioStatus($request, $this->receipt($request));
            $receipts->markProcessed($this->receipt($request));

            return response()->noContent();
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    public function recording(Request $request, WebhookReceiptRecorder $receipts, RecordingProcessor $processor): Response
    {
        try {
            $processor->processTwilio($request, $this->receipt($request));
            $receipts->markProcessed($this->receipt($request));

            return response()->noContent();
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    public function transcription(Request $request, WebhookReceiptRecorder $receipts, TranscriptionProcessor $processor): Response
    {
        try {
            $processor->processTwilio($request, $this->receipt($request));
            $receipts->markProcessed($this->receipt($request));

            return response()->noContent();
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    public function aiStatus(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        return $this->acknowledge($request, $receipts);
    }

    private function acknowledge(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        try {
            $receipts->markProcessed($this->receipt($request));

            return response()->noContent();
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    private function emptyTwiml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response/>';
    }

    private function receipt(Request $request): ?WebhookReceipt
    {
        $receipt = $request->attributes->get('phone_webhook_receipt');

        return $receipt instanceof WebhookReceipt ? $receipt : null;
    }
}
