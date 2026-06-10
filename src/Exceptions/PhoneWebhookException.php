<?php

declare(strict_types=1);

namespace Fissible\Phone\Exceptions;

use RuntimeException;

class PhoneWebhookException extends RuntimeException
{
    public static function missingField(string $field): self
    {
        return new self("Twilio webhook payload is missing required field [{$field}].");
    }

    public static function unknownLocalNumber(string $phoneNumber): self
    {
        return new self("No local phone number is configured for inbound number [{$phoneNumber}].");
    }
}
