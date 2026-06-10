<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\ValueObjects\OutboundMessage;

interface MessagePolicy
{
    public function assertCanSend(
        OutboundMessage $message,
        ?PhoneNumber $phoneNumber = null,
        ?PhoneThread $thread = null,
    ): void;
}
