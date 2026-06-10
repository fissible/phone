<?php

declare(strict_types=1);

namespace Fissible\Phone\Http\Middleware;

use Closure;
use Fissible\Phone\Services\WebhookReceiptRecorder;
use Fissible\Phone\Twilio\TwilioWebhookValidator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTwilioWebhook
{
    public function __construct(
        private readonly Repository $config,
        private readonly TwilioWebhookValidator $validator,
        private readonly WebhookReceiptRecorder $receipts,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $validationUrl = $this->validator->publicUrlFor($request);
        $signatureValid = true;

        if ($this->shouldValidate()) {
            $signatureValid = $this->validator->validate($request, $validationUrl);
        }

        $receipt = $this->receipts->record(
            $request,
            $this->eventType($request),
            $signatureValid,
            $validationUrl,
        );

        $request->attributes->set('phone_webhook_receipt', $receipt);

        if (! $signatureValid) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid Twilio webhook signature.');
        }

        return $next($request);
    }

    private function shouldValidate(): bool
    {
        return (bool) $this->config->get('phone.twilio.validate_webhooks', true);
    }

    private function eventType(Request $request): string
    {
        $route = $request->route();
        $eventType = $route?->parameter('phone_event_type')
            ?? ($route?->defaults['phone_event_type'] ?? null);

        return is_string($eventType) && $eventType !== ''
            ? $eventType
            : 'webhook';
    }
}
