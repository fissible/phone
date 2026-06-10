<?php

declare(strict_types=1);

namespace Fissible\Phone\Exceptions;

use RuntimeException;

class PhoneConfigurationException extends RuntimeException
{
    public static function missingTwilioCredentials(): self
    {
        return new self('Twilio credentials are missing. Set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN.');
    }

    public static function missingSender(): self
    {
        return new self('No Twilio sender is configured. Set TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM, or pass a sender explicitly.');
    }

    public static function unsupportedProvider(string $provider): self
    {
        return new self("Unsupported phone provider [{$provider}].");
    }
}
