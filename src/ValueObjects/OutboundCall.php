<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class OutboundCall
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $to,
        public ?string $from = null,
        public ?string $twiml = null,
        public ?string $url = null,
        public ?string $statusCallbackUrl = null,
        public ?string $machineDetection = null,
        public ?int $timeout = null,
        public array $metadata = [],
        public ?ContactIdentity $contact = null,
    ) {}
}
