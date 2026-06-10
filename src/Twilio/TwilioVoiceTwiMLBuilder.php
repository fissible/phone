<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

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

        $response->record($attributes);
    }
}
