<?php

declare(strict_types=1);

namespace Fissible\Phone\Jobs;

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Events\OutboundCallFailed;
use Fissible\Phone\Events\OutboundCallInitiated;
use Fissible\Phone\Exceptions\PhoneCallException;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\ValueObjects\OutboundCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class SendOutboundCall implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $callId,
    ) {}

    public function handle(PhoneProvider $provider, Dispatcher $events): ?PhoneCall
    {
        $claimed = PhoneCall::query()
            ->whereKey($this->callId)
            ->where('status', CallStatus::QUEUED)
            ->whereNull('provider_call_sid')
            ->update([
                'status' => CallStatus::SENDING,
                'status_rank' => CallStatus::rank(CallStatus::SENDING),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return PhoneCall::query()->find($this->callId);
        }

        /** @var PhoneCall|null $call */
        $call = PhoneCall::query()->find($this->callId);

        if (! $call instanceof PhoneCall
            || $call->provider_call_sid !== null
            || $call->status !== CallStatus::SENDING) {
            return $call;
        }

        try {
            $receipt = $provider->createCall($this->outboundCall($call));
        } catch (PhoneConfigurationException|PhoneCallException $exception) {
            $this->markFailed($call, $exception);
            $call->refresh();
            $events->dispatch(new OutboundCallFailed($call, $exception));

            throw $exception;
        } catch (Throwable $exception) {
            $this->markSendUnknown($call, $exception);
            $call->refresh();
            $events->dispatch(new OutboundCallFailed($call, $exception));

            return $call;
        }

        $call->forceFill([
            'provider_call_sid' => $receipt->providerCallSid,
            'status' => CallStatus::INITIATED,
            'status_rank' => CallStatus::rank(CallStatus::INITIATED),
            'started_at' => now(),
            'metadata' => array_replace_recursive($call->metadata ?? [], [
                'provider_response' => $receipt->raw,
            ]),
        ])->save();

        $call->refresh();
        $events->dispatch(new OutboundCallInitiated($call));

        return $call;
    }

    private function outboundCall(PhoneCall $call): OutboundCall
    {
        $metadata = $call->metadata ?? [];
        $outbound = is_array($metadata['outbound'] ?? null) ? $metadata['outbound'] : [];

        return new OutboundCall(
            to: $call->to_number,
            from: $call->from_number,
            twiml: $this->nullableString($outbound['twiml'] ?? null),
            url: $this->nullableString($outbound['url'] ?? null),
            statusCallbackUrl: $this->nullableString($outbound['status_callback_url'] ?? null),
            machineDetection: $this->nullableString($outbound['machine_detection'] ?? null),
            timeout: is_numeric($outbound['timeout'] ?? null) ? (int) $outbound['timeout'] : null,
            metadata: $metadata,
        );
    }

    private function markFailed(PhoneCall $call, Throwable $exception): void
    {
        $call->forceFill([
            'status' => CallStatus::FAILED,
            'status_rank' => CallStatus::rank(CallStatus::FAILED),
            'ended_at' => now(),
            'metadata' => array_replace_recursive($call->metadata ?? [], [
                'failure' => [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]),
        ])->save();
    }

    private function markSendUnknown(PhoneCall $call, Throwable $exception): void
    {
        $call->forceFill([
            'status' => CallStatus::SEND_UNKNOWN,
            'status_rank' => CallStatus::rank(CallStatus::SEND_UNKNOWN),
            'metadata' => array_replace_recursive($call->metadata ?? [], [
                'send_unknown' => [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]),
        ])->save();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
