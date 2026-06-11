<?php

declare(strict_types=1);

namespace Fissible\Phone\Contracts;

use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\ContactLookup;

interface ContactResolver
{
    public function resolve(ContactLookup $lookup): ContactIdentity;
}
