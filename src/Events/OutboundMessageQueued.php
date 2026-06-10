<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneMessage;

class OutboundMessageQueued
{
    public function __construct(
        public readonly PhoneMessage $message,
    ) {}
}
