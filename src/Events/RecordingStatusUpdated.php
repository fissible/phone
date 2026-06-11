<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneRecording;
use Fissible\Phone\Models\WebhookReceipt;

class RecordingStatusUpdated
{
    public function __construct(
        public readonly PhoneRecording $recording,
        public readonly ?string $oldStatus,
        public readonly string $newStatus,
        public readonly ?WebhookReceipt $webhookReceipt = null,
    ) {}
}
