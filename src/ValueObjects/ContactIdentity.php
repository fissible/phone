<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class ContactIdentity
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $displayName,
        public ?string $externalType = null,
        public ?string $externalId = null,
        public ?string $url = null,
        public array $metadata = [],
        public bool $resolved = true,
    ) {}

    public static function anonymous(string $phoneNumber): self
    {
        return new self(
            displayName: $phoneNumber,
            resolved: false,
        );
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'display_name' => $this->displayName,
            'external_type' => $this->externalType,
            'external_id' => $this->externalId,
            'url' => $this->url,
            'metadata' => $this->metadata,
            'resolved' => $this->resolved,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            displayName: is_string($values['display_name'] ?? null) ? $values['display_name'] : 'Unknown',
            externalType: is_string($values['external_type'] ?? null) ? $values['external_type'] : null,
            externalId: is_string($values['external_id'] ?? null) ? $values['external_id'] : null,
            url: is_string($values['url'] ?? null) ? $values['url'] : null,
            metadata: is_array($values['metadata'] ?? null) ? $values['metadata'] : [],
            resolved: (bool) ($values['resolved'] ?? false),
        );
    }
}
