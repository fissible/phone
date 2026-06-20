<?php

declare(strict_types=1);

use Fissible\Phone\Facades\Phone;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Numbers\PhoneNumberLookup;

it('exposes a number lookup through the facade', function (): void {
    expect(Phone::numbers())->toBeInstanceOf(PhoneNumberLookup::class);
});

it('finds a phone number by its e164 number', function (): void {
    $number = PhoneNumber::query()->create([
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    expect(Phone::numbers()->findByNumber('+16615550100')->is($number))->toBeTrue()
        ->and(Phone::numbers()->findByNumber('+16619999999'))->toBeNull();
});

it('scopes the lookup by scope key when provided', function (): void {
    PhoneNumber::query()->create([
        'scope_key' => 'tenant:acme',
        'phone_number' => '+16615550100',
        'status' => 'active',
    ]);

    expect(Phone::numbers()->findByNumber('+16615550100', 'tenant:other'))->toBeNull()
        ->and(Phone::numbers()->findByNumber('+16615550100', 'tenant:acme'))->not->toBeNull();
});
