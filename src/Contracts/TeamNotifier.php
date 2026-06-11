<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\ValueObjects\TeamNotification;

interface TeamNotifier
{
    public function notify(TeamNotification $notification): void;
}
