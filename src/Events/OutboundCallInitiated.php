<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneCall;

class OutboundCallInitiated
{
    public function __construct(
        public readonly PhoneCall $call,
    ) {}
}
