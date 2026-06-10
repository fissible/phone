<?php

declare(strict_types=1);

namespace Fissible\Phone\Http\Controllers;

use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Services\WebhookReceiptRecorder;
use Fissible\Phone\Sms\InboundSmsProcessor;
use Fissible\Phone\Sms\MessageStatusProcessor;
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

    public function inboundVoice(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        return $this->emptyTwiml($request, $receipts);
    }

    public function dialStatus(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        return $this->acknowledge($request, $receipts);
    }

    public function voiceStatus(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        return $this->acknowledge($request, $receipts);
    }

    public function recording(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        return $this->acknowledge($request, $receipts);
    }

    public function transcription(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        return $this->acknowledge($request, $receipts);
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

    private function emptyTwiml(Request $request, WebhookReceiptRecorder $receipts): Response
    {
        try {
            $receipts->markProcessed($this->receipt($request));

            return response('<Response></Response>', Response::HTTP_OK, [
                'Content-Type' => 'text/xml',
            ]);
        } catch (Throwable $exception) {
            $receipts->markFailed($this->receipt($request), $exception);

            throw $exception;
        }
    }

    private function receipt(Request $request): ?WebhookReceipt
    {
        $receipt = $request->attributes->get('phone_webhook_receipt');

        return $receipt instanceof WebhookReceipt ? $receipt : null;
    }
}
