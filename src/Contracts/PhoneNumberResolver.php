<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\Models\PhoneNumber;

interface PhoneNumberResolver
{
    public function resolveForInbound(string $localNumber, ?string $providerAccountSid = null): PhoneNumber;
}
