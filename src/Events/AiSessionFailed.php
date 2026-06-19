<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneAiSession;
use Fissible\Phone\Models\WebhookReceipt;

class AiSessionFailed
{
    public function __construct(
        public readonly PhoneAiSession $session,
        public readonly ?WebhookReceipt $webhookReceipt = null,
    ) {}
}
