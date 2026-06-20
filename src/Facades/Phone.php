<?php

declare(strict_types=1);

namespace Fissible\Phone\Facades;

use Fissible\Phone\Calls\PendingOutboundCall;
use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Messages\PendingOutboundMessage;
use Fissible\Phone\Numbers\PhoneNumberLookup;
use Fissible\Phone\PhoneManager;
use Fissible\Phone\Testing\FakePhoneProvider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PhoneProvider provider()
 * @method static PendingOutboundMessage messages()
 * @method static PendingOutboundCall calls()
 * @method static PhoneNumberLookup numbers()
 * @method static FakePhoneProvider fake(?FakePhoneProvider $fake = null)
 *
 * @see PhoneManager
 */
class Phone extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PhoneManager::class;
    }
}
