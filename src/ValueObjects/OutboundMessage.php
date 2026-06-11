<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class OutboundMessage
{
    /**
     * @param  list<string>  $mediaUrls
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $to,
        public ?string $from,
        public ?string $messagingServiceSid,
        public ?string $body,
        public array $mediaUrls = [],
        public ?string $statusCallbackUrl = null,
        public array $metadata = [],
        public ?ContactIdentity $contact = null,
    ) {}
}
