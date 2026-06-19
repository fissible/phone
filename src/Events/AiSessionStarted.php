<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneAiSession;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;

class AiSessionStarted
{
    public function __construct(
        public readonly PhoneAiSession $session,
        public readonly PhoneCall $call,
        public readonly PhoneNumber $phoneNumber,
    ) {}
}
