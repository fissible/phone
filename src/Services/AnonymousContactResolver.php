<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\ContactResolver;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\ContactLookup;

class AnonymousContactResolver implements ContactResolver
{
    public function resolve(ContactLookup $lookup): ContactIdentity
    {
        return ContactIdentity::anonymous($lookup->remoteNumber);
    }
}
