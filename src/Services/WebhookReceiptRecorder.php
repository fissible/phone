<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Models\WebhookReceipt;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class WebhookReceiptRecorder
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function record(Request $request, string $eventType, bool $signatureValid, string $validationUrl): WebhookReceipt
    {
        $signature = (string) $request->headers->get('X-Twilio-Signature', '');
        $attributes = [
            'provider' => 'twilio',
            'event_type' => $eventType,
            'request_hash' => $this->requestHash($request, $validationUrl, $signature),
        ];

        $receipt = WebhookReceipt::query()->firstOrNew($attributes);

        if ($receipt->exists) {
            if ($signatureValid && ! $receipt->signature_valid) {
                $receipt->forceFill([
                    'signature_valid' => true,
                    'processing_status' => 'pending',
                    'headers' => $this->headers($request),
                    'payload' => $this->payload($request, $signatureValid),
                ])->save();
            }

            return $receipt;
        }

        $receipt->forceFill($attributes + [
            'provider_sid' => $this->providerSid($request, $eventType),
            'request_method' => $request->getMethod(),
            'request_url' => $validationUrl,
            'source_ip' => $request->ip(),
            'signature_valid' => $signatureValid,
            'headers' => $this->headers($request),
            'payload' => $this->payload($request, $signatureValid),
            'processing_status' => $signatureValid ? 'pending' : 'rejected',
        ])->save();

        return $receipt;
    }

    public function markProcessed(?WebhookReceipt $receipt): void
    {
        $receipt?->markProcessed();
    }

    public function markFailed(?WebhookReceipt $receipt, Throwable $exception): void
    {
        $receipt?->markFailed($exception);
    }

    private function requestHash(Request $request, string $validationUrl, string $signature): string
    {
        return hash('sha256', implode("\n", [
            $request->getMethod(),
            $validationUrl,
            $request->getContent(),
            $signature,
        ]));
    }

    /** @return array<string, mixed> */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return $this->redact($headers, ['authorization', 'cookie', 'set-cookie']);
    }

    /** @return array<string, mixed>|null */
    private function payload(Request $request, bool $signatureValid): ?array
    {
        if (! $this->shouldStorePayload($signatureValid)) {
            return null;
        }

        $payload = $request->request->all();
        $content = $request->getContent();

        if ($content !== '' && str_contains((string) $request->headers->get('content-type'), 'application/json')) {
            $decoded = json_decode($content, true);

            $payload = is_array($decoded) ? $decoded : ['_raw' => $content];
        }

        if ($request->query->all() !== []) {
            $payload['_query'] = $request->query->all();
        }

        return $this->redact($payload);
    }

    private function shouldStorePayload(bool $signatureValid): bool
    {
        if (! $this->config->get('phone.webhooks.store_raw_payloads', true)) {
            return false;
        }

        if ($signatureValid) {
            return true;
        }

        return (bool) $this->config->get('phone.webhooks.store_invalid_payloads', false);
    }

    private function providerSid(Request $request, string $eventType): ?string
    {
        $keys = match ($eventType) {
            'voice.recording' => ['RecordingSid', 'CallSid'],
            'voice.transcription' => ['TranscriptionSid', 'RecordingSid', 'CallSid'],
            'sms.inbound', 'sms.status' => ['MessageSid', 'SmsSid'],
            default => ['CallSid', 'MessageSid', 'SmsSid', 'RecordingSid', 'TranscriptionSid'],
        };

        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $extraKeys
     * @return array<string, mixed>
     */
    private function redact(array $values, array $extraKeys = []): array
    {
        $configured = $this->config->get('phone.webhooks.redact', []);
        $redactedKeys = array_map(
            static fn (string $key): string => strtolower($key),
            array_merge($extraKeys, is_array($configured) ? $configured : []),
        );

        foreach (array_keys(Arr::dot($values)) as $key) {
            $segments = explode('.', (string) $key);
            $leafKey = strtolower((string) end($segments));

            if (in_array($leafKey, $redactedKeys, true)) {
                Arr::set($values, (string) $key, '[redacted]');
            }
        }

        return $values;
    }
}
