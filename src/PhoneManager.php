<?php

declare(strict_types=1);

namespace Fissible\Phone;

use Fissible\Phone\Calls\OutboundCallService;
use Fissible\Phone\Calls\PendingOutboundCall;
use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Messages\PendingOutboundMessage;
use Fissible\Phone\Numbers\PhoneNumberLookup;
use Fissible\Phone\Sms\OutboundMessageService;
use Fissible\Phone\Testing\FakePhoneProvider;
use Illuminate\Contracts\Container\Container;

class PhoneManager
{
    public function __construct(private readonly Container $container) {}

    public function provider(): PhoneProvider
    {
        return $this->container->make(PhoneProvider::class);
    }

    public function messages(): PendingOutboundMessage
    {
        return new PendingOutboundMessage($this->container->make(OutboundMessageService::class));
    }

    public function calls(): PendingOutboundCall
    {
        return new PendingOutboundCall($this->container->make(OutboundCallService::class));
    }

    public function numbers(): PhoneNumberLookup
    {
        return $this->container->make(PhoneNumberLookup::class);
    }

    public function fake(?FakePhoneProvider $fake = null): FakePhoneProvider
    {
        $fake ??= new FakePhoneProvider;

        $this->container->instance(PhoneProvider::class, $fake);

        return $fake;
    }
}
