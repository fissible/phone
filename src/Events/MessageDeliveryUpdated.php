<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\WebhookReceipt;

class MessageDeliveryUpdated
{
    public function __construct(
        public readonly PhoneMessage $message,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $providerStatus,
        public readonly ?WebhookReceipt $webhookReceipt = null,
    ) {}
}
