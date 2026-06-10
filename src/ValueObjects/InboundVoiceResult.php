<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;

final readonly class InboundVoiceResult
{
    public function __construct(
        public PhoneCall $call,
        public PhoneNumber $phoneNumber,
        public RouteDecision $decision,
        public string $twiml,
    ) {}
}
