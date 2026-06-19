<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class ConversationRelayConfig
{
    /**
     * @param  array<string, scalar>  $parameters  Signed/auth <Parameter> children passed to the relay.
     * @param  array<string, scalar>  $attributes  Extra ConversationRelay attributes rendered verbatim.
     * @param  array<string, mixed>  $metadata  Persisted alongside the session, never rendered.
     */
    public function __construct(
        public string $websocketUrl,
        public ?string $voice = null,
        public ?string $language = null,
        public ?string $welcomeGreeting = null,
        public bool $interruptible = true,
        public array $parameters = [],
        public array $attributes = [],
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'websocket_url' => $this->websocketUrl,
            'voice' => $this->voice,
            'language' => $this->language,
            'welcome_greeting' => $this->welcomeGreeting,
            'interruptible' => $this->interruptible,
            'parameters' => $this->parameters,
            'attributes' => $this->attributes,
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            websocketUrl: is_string($values['websocket_url'] ?? null) ? $values['websocket_url'] : '',
            voice: is_string($values['voice'] ?? null) ? $values['voice'] : null,
            language: is_string($values['language'] ?? null) ? $values['language'] : null,
            welcomeGreeting: is_string($values['welcome_greeting'] ?? null) ? $values['welcome_greeting'] : null,
            interruptible: (bool) ($values['interruptible'] ?? true),
            parameters: is_array($values['parameters'] ?? null) ? $values['parameters'] : [],
            attributes: is_array($values['attributes'] ?? null) ? $values['attributes'] : [],
            metadata: is_array($values['metadata'] ?? null) ? $values['metadata'] : [],
        );
    }
}
