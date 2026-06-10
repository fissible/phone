<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\MessagePolicy;
use Fissible\Phone\Exceptions\PhoneMessageException;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Illuminate\Contracts\Config\Repository;

class DefaultMessagePolicy implements MessagePolicy
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function assertCanSend(
        OutboundMessage $message,
        ?PhoneNumber $phoneNumber = null,
        ?PhoneThread $thread = null,
    ): void {
        if ($thread instanceof PhoneThread && $thread->opted_out_at !== null) {
            throw PhoneMessageException::recipientOptedOut($message->to);
        }

        if (! $thread instanceof PhoneThread && ! $this->allowsUnknownRecipient($message)) {
            throw PhoneMessageException::unknownRecipient($message->to);
        }
    }

    private function allowsUnknownRecipient(OutboundMessage $message): bool
    {
        $policy = is_array($message->metadata['policy'] ?? null)
            ? $message->metadata['policy']
            : [];

        if (($policy['allow_unknown_recipient'] ?? null) === true) {
            return true;
        }

        if (($policy['allow_unknown_recipient'] ?? null) === false) {
            return false;
        }

        return (bool) $this->config->get('phone.sms.allow_unknown_recipients', false);
    }
}
