<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\ValueObjects\ContactIdentity;

class InboundCallContactResolved
{
    public function __construct(
        public readonly PhoneCall $call,
        public readonly PhoneNumber $phoneNumber,
        public readonly ContactIdentity $contact,
    ) {}
}
