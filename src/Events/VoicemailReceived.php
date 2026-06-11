<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneRecording;
use Fissible\Phone\Models\PhoneVoicemail;
use Fissible\Phone\Models\WebhookReceipt;

class VoicemailReceived
{
    public function __construct(
        public readonly PhoneVoicemail $voicemail,
        public readonly PhoneRecording $recording,
        public readonly ?PhoneCall $call = null,
        public readonly ?WebhookReceipt $webhookReceipt = null,
    ) {}
}
