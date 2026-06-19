<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneCall;
use Throwable;

class OutboundCallFailed
{
    public function __construct(
        public readonly PhoneCall $call,
        public readonly Throwable $exception,
    ) {}
}
