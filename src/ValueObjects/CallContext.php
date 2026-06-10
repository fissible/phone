<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Twilio\TwilioInboundVoicePayload;

final readonly class CallContext
{
    public function __construct(
        public PhoneCall $call,
        public PhoneNumber $phoneNumber,
        public TwilioInboundVoicePayload $payload,
    ) {}
}
