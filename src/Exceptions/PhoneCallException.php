<?php

declare(strict_types=1);

namespace Fissible\Phone\Exceptions;

use InvalidArgumentException;

class PhoneCallException extends InvalidArgumentException
{
    public static function missingRecipient(): self
    {
        return new self('Outbound calls require a recipient.');
    }

    public static function missingInstructions(): self
    {
        return new self('Outbound calls require inline TwiML or a TwiML URL.');
    }
}
