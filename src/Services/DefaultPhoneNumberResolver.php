<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\PhoneNumberResolver;
use Fissible\Phone\Exceptions\PhoneWebhookException;
use Fissible\Phone\Models\PhoneNumber;
use Illuminate\Contracts\Config\Repository;

class DefaultPhoneNumberResolver implements PhoneNumberResolver
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function resolveForInbound(string $localNumber, ?string $providerAccountSid = null): PhoneNumber
    {
        $existing = PhoneNumber::query()
            ->where('phone_number', $localNumber)
            ->orderBy('id')
            ->first();

        if ($existing instanceof PhoneNumber) {
            return $existing;
        }

        if (! $this->config->get('phone.numbers.create_unknown_inbound', true)) {
            throw PhoneWebhookException::unknownLocalNumber($localNumber);
        }

        return PhoneNumber::query()->create([
            'scope_key' => (string) $this->config->get('phone.numbers.default_scope_key', 'global'),
            'scope_type' => $this->nullableString($this->config->get('phone.numbers.default_scope_type')),
            'scope_id' => $this->nullableString($this->config->get('phone.numbers.default_scope_id')),
            'provider' => 'twilio',
            'phone_number' => $localNumber,
            'provider_account_sid' => $providerAccountSid,
            'capabilities' => [
                'sms' => true,
                'mms' => true,
                'voice' => true,
            ],
            'status' => 'active',
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
