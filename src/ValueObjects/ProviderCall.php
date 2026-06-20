<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class ProviderCall
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $provider,
        public string $providerCallSid,
        public string $status,
        public array $raw = [],
    ) {}
}
