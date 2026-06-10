<?php

declare(strict_types=1);

namespace Fissible\Phone\Exceptions;

use InvalidArgumentException;

class PhoneMessageException extends InvalidArgumentException
{
    public static function missingRecipient(): self
    {
        return new self('Outbound messages require a recipient.');
    }

    public static function missingBodyAndMedia(): self
    {
        return new self('Outbound messages require a body or at least one media URL.');
    }

    public static function recipientOptedOut(string $recipient): self
    {
        return new self("Outbound messages to [{$recipient}] are blocked because the thread is opted out.");
    }

    public static function unknownRecipient(string $recipient): self
    {
        return new self("Outbound messages to [{$recipient}] are blocked because the recipient is unknown.");
    }
}
