<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;

final readonly class ContactLookup
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $channel,
        public string $direction,
        public string $localNumber,
        public string $remoteNumber,
        public PhoneNumber $phoneNumber,
        public ?PhoneThread $thread = null,
        public ?PhoneCall $call = null,
        public array $metadata = [],
    ) {}

    public function scopeKey(): string
    {
        return $this->phoneNumber->scope_key;
    }

    public function scopeType(): ?string
    {
        return $this->phoneNumber->scope_type;
    }

    public function scopeId(): ?string
    {
        return $this->phoneNumber->scope_id;
    }
}
