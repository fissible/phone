<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\Twilio\TwilioInboundSmsPayload;

class SmsThreadResolver
{
    public function resolveInbound(PhoneNumber $phoneNumber, TwilioInboundSmsPayload $payload): PhoneThread
    {
        return PhoneThread::query()->firstOrCreate([
            'scope_key' => $phoneNumber->scope_key,
            'phone_number_id' => $phoneNumber->getKey(),
            'remote_number' => $payload->from,
        ], [
            'scope_type' => $phoneNumber->scope_type,
            'scope_id' => $phoneNumber->scope_id,
            'provider' => 'twilio',
            'local_number' => $payload->to,
            'metadata' => [],
        ]);
    }
}
