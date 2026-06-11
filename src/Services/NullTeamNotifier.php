<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\TeamNotifier;
use Fissible\Phone\ValueObjects\TeamNotification;

class NullTeamNotifier implements TeamNotifier
{
    public function notify(TeamNotification $notification): void
    {
        //
    }
}
