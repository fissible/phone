<?php

declare(strict_types=1);

namespace Fissible\Phone\Calls;

use Fissible\Phone\Exceptions\PhoneCallException;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\OutboundCall;

class PendingOutboundCall
{
    private ?string $to = null;

    private ?string $from = null;

    private ?string $twiml = null;

    private ?string $url = null;

    private ?string $statusCallbackUrl = null;

    private ?string $machineDetection = null;

    private ?int $timeout = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    private ?ContactIdentity $contact = null;

    public function __construct(private readonly OutboundCallService $calls) {}

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

    /** Alias for from(): the outbound caller ID. */
    public function callerId(string $number): self
    {
        return $this->from($number);
    }

    public function twiml(string $twiml): self
    {
        $this->twiml = $twiml;

        return $this;
    }

    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function statusCallbackUrl(string $url): self
    {
        $this->statusCallbackUrl = $url;

        return $this;
    }

    public function detectMachine(string $mode = 'Enable'): self
    {
        $this->machineDetection = $mode;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /** @param array<string, mixed> $metadata */
    public function contact(string $type, string|int $id, string $name, ?string $url = null, array $metadata = []): self
    {
        $this->contact = new ContactIdentity(
            displayName: $name,
            externalType: $type,
            externalId: (string) $id,
            url: $url,
            metadata: $metadata,
        );

        return $this;
    }

    public function contactIdentity(ContactIdentity $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function send(): PhoneCall
    {
        return $this->calls->send($this->outboundCall());
    }

    public function queue(): PhoneCall
    {
        return $this->calls->queue($this->outboundCall());
    }

    private function outboundCall(): OutboundCall
    {
        if ($this->to === null || $this->to === '') {
            throw PhoneCallException::missingRecipient();
        }

        if (($this->twiml === null || $this->twiml === '') && ($this->url === null || $this->url === '')) {
            throw PhoneCallException::missingInstructions();
        }

        return new OutboundCall(
            to: $this->to,
            from: $this->from,
            twiml: $this->twiml,
            url: $this->url,
            statusCallbackUrl: $this->statusCallbackUrl,
            machineDetection: $this->machineDetection,
            timeout: $this->timeout,
            metadata: $this->metadata,
            contact: $this->contact,
        );
    }
}
