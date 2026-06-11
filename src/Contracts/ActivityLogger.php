<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\ValueObjects\PhoneActivity;

interface ActivityLogger
{
    public function log(PhoneActivity $activity): void;
}
