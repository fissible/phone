<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneMessage;
use Throwable;

class OutboundMessageFailed
{
    public function __construct(
        public readonly PhoneMessage $message,
        public readonly ?Throwable $exception = null,
    ) {}
}
