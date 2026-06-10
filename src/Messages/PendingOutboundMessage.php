<?php

declare(strict_types=1);

namespace Fissible\Phone\Messages;

use Fissible\Phone\Exceptions\PhoneMessageException;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Sms\OutboundMessageService;
use Fissible\Phone\ValueObjects\OutboundMessage;

class PendingOutboundMessage
{
    private ?string $to = null;

    private ?string $from = null;

    private ?string $messagingServiceSid = null;

    private ?string $body = null;

    /** @var list<string> */
    private array $mediaUrls = [];

    private ?string $statusCallbackUrl = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(private readonly OutboundMessageService $messages) {}

    public function to(string $number): self
    {
        $this->to = $number;

        return $this;
    }

    public function from(string $number): self
    {
        $this->from = $number;

        return $this;
    }

    public function messagingServiceSid(string $sid): self
    {
        $this->messagingServiceSid = $sid;

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function media(string $url): self
    {
        $this->mediaUrls[] = $url;

        return $this;
    }

    /** @param list<string> $urls */
    public function mediaUrls(array $urls): self
    {
        $this->mediaUrls = array_values($urls);

        return $this;
    }

    public function statusCallbackUrl(string $url): self
    {
        $this->statusCallbackUrl = $url;

        return $this;
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function allowUnknownRecipient(bool $allowed = true): self
    {
        $this->metadata = array_replace_recursive($this->metadata, [
            'policy' => [
                'allow_unknown_recipient' => $allowed,
            ],
        ]);

        return $this;
    }

    public function send(): PhoneMessage
    {
        return $this->messages->send($this->outboundMessage());
    }

    public function queue(): PhoneMessage
    {
        return $this->messages->queue($this->outboundMessage());
    }

    private function outboundMessage(): OutboundMessage
    {
        if ($this->to === null || $this->to === '') {
            throw PhoneMessageException::missingRecipient();
        }

        if (($this->body === null || $this->body === '') && $this->mediaUrls === []) {
            throw PhoneMessageException::missingBodyAndMedia();
        }

        return new OutboundMessage(
            to: $this->to,
            from: $this->from,
            messagingServiceSid: $this->messagingServiceSid,
            body: $this->body,
            mediaUrls: $this->mediaUrls,
            statusCallbackUrl: $this->statusCallbackUrl,
            metadata: $this->metadata,
        );
    }
}
