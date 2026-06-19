<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\ValueObjects\OutboundCall;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Fissible\Phone\ValueObjects\ProviderCall;
use Fissible\Phone\ValueObjects\ProviderMessage;

interface PhoneProvider
{
    public function sendMessage(OutboundMessage $message): ProviderMessage;

    public function createCall(OutboundCall $call): ProviderCall;
}
