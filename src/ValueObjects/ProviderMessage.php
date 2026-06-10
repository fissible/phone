<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class ProviderMessage
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $provider,
        public string $providerMessageSid,
        public string $status,
        public array $raw = [],
    ) {}
}
