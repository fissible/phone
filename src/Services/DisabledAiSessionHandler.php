<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\AiSessionHandler;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\ValueObjects\CallContext;
use Fissible\Phone\ValueObjects\ConversationRelayConfig;

class DisabledAiSessionHandler implements AiSessionHandler
{
    public function shouldHandle(CallContext $call): bool
    {
        return false;
    }

    public function configure(CallContext $call): ConversationRelayConfig
    {
        throw PhoneConfigurationException::aiHandoffDisabled();
    }
}
