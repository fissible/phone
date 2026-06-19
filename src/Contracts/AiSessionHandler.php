<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\ValueObjects\CallContext;
use Fissible\Phone\ValueObjects\ConversationRelayConfig;

interface AiSessionHandler
{
    /**
     * Whether this inbound call should be handed off to AI answering.
     */
    public function shouldHandle(CallContext $call): bool;

    /**
     * Build the Conversation Relay settings for an AI-handled call.
     */
    public function configure(CallContext $call): ConversationRelayConfig;
}
