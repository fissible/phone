<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\WebhookReceipt;

class CallStatusUpdated
{
    public function __construct(
        public readonly PhoneCall $call,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?string $providerStatus = null,
        public readonly ?WebhookReceipt $webhookReceipt = null,
    ) {}
}
