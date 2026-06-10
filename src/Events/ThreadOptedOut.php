<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneThread;

class ThreadOptedOut
{
    public function __construct(
        public readonly PhoneThread $thread,
        public readonly PhoneMessage $message,
        public readonly string $keyword,
    ) {}
}
