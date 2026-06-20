<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneCall;

class OutboundCallQueued
{
    public function __construct(
        public readonly PhoneCall $call,
    ) {}
}
