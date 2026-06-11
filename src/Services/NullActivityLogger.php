<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\ActivityLogger;
use Fissible\Phone\ValueObjects\PhoneActivity;

class NullActivityLogger implements ActivityLogger
{
    public function log(PhoneActivity $activity): void
    {
        //
    }
}
