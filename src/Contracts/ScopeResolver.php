<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\ValueObjects\Scope;

interface ScopeResolver
{
    public function resolve(): Scope;
}
