<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\ValueObjects\OptOutResult;

interface OptOutPolicy
{
    public function applyInbound(PhoneThread $thread, PhoneMessage $message): ?OptOutResult;
}
