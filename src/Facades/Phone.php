<?php

declare(strict_types=1);

namespace Fissible\Phone\Facades;

use Fissible\Phone\Messages\PendingOutboundMessage;
use Fissible\Phone\PhoneManager;
use Fissible\Phone\Testing\FakePhoneProvider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingOutboundMessage messages()
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
