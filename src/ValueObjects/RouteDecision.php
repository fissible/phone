<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class RouteDecision
{
    public const FORWARD = 'forward';

    public const VOICEMAIL = 'voicemail';

    public const REJECT = 'reject';

    public const HANGUP = 'hangup';

    public const AI = 'ai';

    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $type,
        public ?string $forwardTo = null,
        public int $timeout = 20,
        public ?string $actionUrl = null,
        public ?string $recordingStatusCallbackUrl = null,
        public bool $transcribe = false,
        public ?string $transcriptionCallbackUrl = null,
        public ?string $greeting = null,
        public ?ConversationRelayConfig $conversationRelay = null,
        public array $metadata = [],
    ) {}

    public static function forward(string $forwardTo, int $timeout, ?string $actionUrl = null): self
    {
        return new self(
            type: self::FORWARD,
            forwardTo: $forwardTo,
            timeout: $timeout,
            actionUrl: $actionUrl,
        );
    }

    public static function voicemail(
        ?string $greeting = null,
        ?string $recordingStatusCallbackUrl = null,
        bool $transcribe = false,
        ?string $transcriptionCallbackUrl = null,
    ): self {
        return new self(
            type: self::VOICEMAIL,
            recordingStatusCallbackUrl: $recordingStatusCallbackUrl,
            transcribe: $transcribe,
            transcriptionCallbackUrl: $transcriptionCallbackUrl,
            greeting: $greeting,
        );
    }

    public static function reject(): self
    {
        return new self(type: self::REJECT);
    }

    public static function hangup(): self
    {
        return new self(type: self::HANGUP);
    }

    /** @param array<string, mixed> $metadata */
    public static function ai(ConversationRelayConfig $conversationRelay, array $metadata = []): self
    {
        return new self(
            type: self::AI,
            conversationRelay: $conversationRelay,
            metadata: $metadata,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $values = [
            'type' => $this->type,
        ];

        if ($this->type === self::FORWARD) {
            $values['forward_to'] = $this->forwardTo;
            $values['timeout'] = $this->timeout;
            $values['action_url'] = $this->actionUrl;
        }

        if ($this->type === self::VOICEMAIL) {
            $values['recording_status_callback_url'] = $this->recordingStatusCallbackUrl;
            $values['transcribe'] = $this->transcribe;
            $values['transcription_callback_url'] = $this->transcriptionCallbackUrl;
            $values['greeting'] = $this->greeting;
        }

        if ($this->type === self::AI && $this->conversationRelay instanceof ConversationRelayConfig) {
            $values['conversation_relay'] = $this->conversationRelay->toArray();
        }

        if ($this->metadata !== []) {
            $values['metadata'] = $this->metadata;
        }

        return array_filter($values, static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            type: is_string($values['type'] ?? null) ? $values['type'] : self::HANGUP,
            forwardTo: is_string($values['forward_to'] ?? null) ? $values['forward_to'] : null,
            timeout: is_numeric($values['timeout'] ?? null) ? (int) $values['timeout'] : 20,
            actionUrl: is_string($values['action_url'] ?? null) ? $values['action_url'] : null,
            recordingStatusCallbackUrl: is_string($values['recording_status_callback_url'] ?? null)
                ? $values['recording_status_callback_url']
                : null,
            transcribe: (bool) ($values['transcribe'] ?? false),
            transcriptionCallbackUrl: is_string($values['transcription_callback_url'] ?? null)
                ? $values['transcription_callback_url']
                : null,
            greeting: is_string($values['greeting'] ?? null) ? $values['greeting'] : null,
            conversationRelay: is_array($values['conversation_relay'] ?? null)
                ? ConversationRelayConfig::fromArray($values['conversation_relay'])
                : null,
            metadata: is_array($values['metadata'] ?? null) ? $values['metadata'] : [],
        );
    }
}
