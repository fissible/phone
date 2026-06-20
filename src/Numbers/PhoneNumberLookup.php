<?php

declare(strict_types=1);

namespace Fissible\Phone\Numbers;

use Fissible\Phone\Models\PhoneNumber;

class PhoneNumberLookup
{
    public function findByNumber(string $number, ?string $scopeKey = null): ?PhoneNumber
    {
        $query = PhoneNumber::query()->where('phone_number', $number);

        if ($scopeKey !== null) {
            $query->where('scope_key', $scopeKey);
        }

        /** @var PhoneNumber|null $result */
        $result = $query->first();

        return $result;
    }
}
