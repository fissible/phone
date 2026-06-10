<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\ValueObjects\CallContext;
use Fissible\Phone\ValueObjects\RouteDecision;

interface CallRouter
{
    public function route(CallContext $call): RouteDecision;
}
