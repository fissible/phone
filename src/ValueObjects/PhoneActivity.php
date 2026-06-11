<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

use DateTimeInterface;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Models\WebhookReceipt;

final readonly class PhoneActivity
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $type,
        public string $channel,
        public string $direction,
        public DateTimeInterface $occurredAt,
        public ?PhoneNumber $phoneNumber = null,
        public ?PhoneThread $thread = null,
        public ?PhoneMessage $message = null,
        public ?PhoneCall $call = null,
        public ?ContactIdentity $contact = null,
        public ?WebhookReceipt $webhookReceipt = null,
        public array $metadata = [],
    ) {}
}
