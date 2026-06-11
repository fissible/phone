<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\CallRouter;
use Fissible\Phone\ValueObjects\CallContext;
use Fissible\Phone\ValueObjects\RouteDecision;
use Illuminate\Contracts\Config\Repository;

class DefaultCallRouter implements CallRouter
{
    public function __construct(
        private readonly Repository $config,
        private readonly BusinessHours $businessHours,
    ) {}

    public function route(CallContext $call): RouteDecision
    {
        $mode = $this->string($call->phoneNumber->routing_mode)
            ?: $this->string($this->config->get('phone.default_voice.mode'))
            ?: RouteDecision::FORWARD;

        if ($mode === RouteDecision::FORWARD && ! $this->businessHours->isOpen($call->phoneNumber)) {
            $hours = $this->businessHours->hoursFor($call->phoneNumber);
            $mode = $this->businessHours->afterHoursMode($hours)
                ?: $this->string($this->config->get('phone.default_voice.after_hours_mode'))
                ?: RouteDecision::VOICEMAIL;
        }

        return match ($mode) {
            RouteDecision::REJECT => RouteDecision::reject(),
            RouteDecision::HANGUP => RouteDecision::hangup(),
            RouteDecision::VOICEMAIL => $this->voicemail($call),
            default => $this->forwardOrVoicemail($call),
        };
    }

    private function forwardOrVoicemail(CallContext $call): RouteDecision
    {
        $forwardTo = $this->string($call->phoneNumber->forward_to)
            ?: $this->string($this->config->get('phone.default_voice.forward_to'));

        if ($forwardTo === null) {
            return $this->voicemail($call);
        }

        return RouteDecision::forward(
            forwardTo: $forwardTo,
            timeout: $this->timeout(),
            actionUrl: $this->callbackUrl('twilio/voice/dial-status', [
                'call_id' => (string) $call->call->getKey(),
            ]),
        );
    }

    private function voicemail(CallContext $call): RouteDecision
    {
        $greeting = $this->string($call->phoneNumber->voicemail_greeting)
            ?: $this->string($this->config->get('phone.default_voice.voicemail_greeting'))
            ?: 'Please leave a message after the tone.';

        return RouteDecision::voicemail(
            greeting: $greeting,
            recordingStatusCallbackUrl: $this->callbackUrl('twilio/voice/recording', [
                'call_id' => (string) $call->call->getKey(),
                'purpose' => 'voicemail',
            ]),
            transcribe: (bool) $this->config->get('phone.default_voice.transcribe_voicemails', false),
            transcriptionCallbackUrl: $this->callbackUrl('twilio/voice/transcription', [
                'call_id' => (string) $call->call->getKey(),
                'purpose' => 'voicemail',
            ]),
        );
    }

    private function timeout(): int
    {
        $timeout = $this->config->get('phone.default_voice.timeout', 20);

        if (! is_numeric($timeout)) {
            return 20;
        }

        return max(1, (int) $timeout);
    }

    /** @param array<string, string> $query */
    private function callbackUrl(string $path, array $query = []): string
    {
        $prefix = trim((string) $this->config->get('phone.route_prefix', 'phone'), '/');
        $path = '/'.trim($prefix.'/'.$path, '/');
        $baseUrl = $this->string($this->config->get('phone.webhooks.base_url'));
        $url = $baseUrl !== null ? rtrim($baseUrl, '/').$path : $path;

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
