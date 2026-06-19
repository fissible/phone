<?php

declare(strict_types=1);

namespace Fissible\Phone\Testing;

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\ValueObjects\OutboundCall;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Fissible\Phone\ValueObjects\ProviderCall;
use Fissible\Phone\ValueObjects\ProviderMessage;
use Illuminate\Support\Str;

class FakePhoneProvider implements PhoneProvider
{
    /** @var list<OutboundMessage> */
    private array $messages = [];

    /** @var list<ProviderMessage> */
    private array $receipts = [];

    /** @var list<OutboundCall> */
    private array $calls = [];

    /** @var list<ProviderCall> */
    private array $callReceipts = [];

    public function sendMessage(OutboundMessage $message): ProviderMessage
    {
        $this->messages[] = $message;

        $receipt = new ProviderMessage(
            provider: 'fake',
            providerMessageSid: 'SM'.Str::upper(Str::random(32)),
            status: 'sent',
            raw: [
                'to' => $message->to,
                'from' => $message->from,
                'messaging_service_sid' => $message->messagingServiceSid,
            ],
        );

        $this->receipts[] = $receipt;

        return $receipt;
    }

    public function createCall(OutboundCall $call): ProviderCall
    {
        $this->calls[] = $call;

        $receipt = new ProviderCall(
            provider: 'fake',
            providerCallSid: 'CA'.Str::upper(Str::random(32)),
            status: 'queued',
            raw: [
                'to' => $call->to,
                'from' => $call->from,
            ],
        );

        $this->callReceipts[] = $receipt;

        return $receipt;
    }

    /** @return list<OutboundMessage> */
    public function messages(): array
    {
        return $this->messages;
    }

    /** @return list<ProviderMessage> */
    public function receipts(): array
    {
        return $this->receipts;
    }

    /** @return list<OutboundCall> */
    public function calls(): array
    {
        return $this->calls;
    }

    /** @return list<ProviderCall> */
    public function callReceipts(): array
    {
        return $this->callReceipts;
    }
}
