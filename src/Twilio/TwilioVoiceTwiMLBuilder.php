<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\ValueObjects\ConversationRelayConfig;
use Fissible\Phone\ValueObjects\RouteDecision;
use Twilio\TwiML\VoiceResponse;

class TwilioVoiceTwiMLBuilder
{
    public function build(RouteDecision $decision): string
    {
        $response = new VoiceResponse;

        match ($decision->type) {
            RouteDecision::FORWARD => $this->forward($response, $decision),
            RouteDecision::VOICEMAIL => $this->voicemail($response, $decision),
            RouteDecision::AI => $this->ai($response, $decision),
            RouteDecision::REJECT => $response->reject(),
            default => $response->hangup(),
        };

        return $response->asXML();
    }

    private function forward(VoiceResponse $response, RouteDecision $decision): void
    {
        if ($decision->forwardTo === null || $decision->forwardTo === '') {
            $response->hangup();

            return;
        }

        $attributes = [
            'method' => 'POST',
            'timeout' => $decision->timeout,
            'answerOnBridge' => true,
        ];

        if ($decision->actionUrl !== null) {
            $attributes['action'] = $decision->actionUrl;
        }

        $response->dial($decision->forwardTo, $attributes);
    }

    private function voicemail(VoiceResponse $response, RouteDecision $decision): void
    {
        if ($decision->greeting !== null && $decision->greeting !== '') {
            $response->say($decision->greeting);
        }

        $attributes = [
            'method' => 'POST',
            'playBeep' => true,
            'maxLength' => 120,
        ];

        if ($decision->recordingStatusCallbackUrl !== null) {
            $attributes['recordingStatusCallback'] = $decision->recordingStatusCallbackUrl;
            $attributes['recordingStatusCallbackMethod'] = 'POST';
        }

        if ($decision->transcribe) {
            $attributes['transcribe'] = true;

            if ($decision->transcriptionCallbackUrl !== null) {
                $attributes['transcribeCallback'] = $decision->transcriptionCallbackUrl;
            }
        }

        $response->record($attributes);
    }

    private function ai(VoiceResponse $response, RouteDecision $decision): void
    {
        $config = $decision->conversationRelay;

        if (! $config instanceof ConversationRelayConfig || $config->websocketUrl === '') {
            $response->hangup();

            return;
        }

        $attributes = ['url' => $config->websocketUrl];

        if ($config->voice !== null) {
            $attributes['voice'] = $config->voice;
        }

        if ($config->language !== null) {
            $attributes['language'] = $config->language;
        }

        if ($config->welcomeGreeting !== null) {
            $attributes['welcomeGreeting'] = $config->welcomeGreeting;
        }

        $attributes['interruptible'] = $config->interruptible;

        foreach ($config->attributes as $name => $value) {
            $attributes[$name] = $value;
        }

        $relay = $response->connect()->conversationRelay($attributes);

        foreach ($config->parameters as $name => $value) {
            $relay->parameter(['name' => (string) $name, 'value' => (string) $value]);
        }
    }
}
