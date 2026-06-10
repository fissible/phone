<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Models\WebhookReceipt;

class InboundMessageReceived
{
    public function __construct(
        public readonly PhoneMessage $message,
        public readonly PhoneThread $thread,
        public readonly PhoneNumber $phoneNumber,
        public readonly ?WebhookReceipt $webhookReceipt = null,
    ) {}
}
