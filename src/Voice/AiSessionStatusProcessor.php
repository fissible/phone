<?php

declare(strict_types=1);

namespace Fissible\Phone\Voice;

use Fissible\Phone\Events\AiSessionEnded;
use Fissible\Phone\Events\AiSessionFailed;
use Fissible\Phone\Models\PhoneAiSession;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\WebhookReceipt;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;

class AiSessionStatusProcessor
{
    /** @var list<string> */
    private const FAILED_STATUSES = ['failed', 'error'];

    /** @var list<string> */
    private const ENDED_STATUSES = ['completed', 'ended', 'disconnected', 'stopped'];

    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function processTwilio(Request $request, ?WebhookReceipt $receipt = null): ?PhoneAiSession
    {
        $callSid = trim((string) $request->input('CallSid', ''));
        $status = strtolower(trim((string) ($request->input('SessionStatus') ?? $request->input('Status') ?? '')));

        if ($callSid === '') {
            return null;
        }

        $failed = in_array($status, self::FAILED_STATUSES, true);
        $ended = in_array($status, self::ENDED_STATUSES, true);

        if (! $failed && ! $ended) {
            return null;
        }

        $call = PhoneCall::query()
            ->where('provider', 'twilio')
            ->where('provider_call_sid', $callSid)
            ->first();

        if (! $call instanceof PhoneCall) {
            return null;
        }

        /** @var PhoneAiSession|null $session */
        $session = PhoneAiSession::query()
            ->where('phone_call_id', $call->getKey())
            ->latest('id')
            ->first();

        if (! $session instanceof PhoneAiSession) {
            return null;
        }

        // Idempotent: a retried terminal callback must not re-dispatch events.
        if ($session->ended_at !== null) {
            return $session;
        }

        $sessionSid = $request->input('SessionId') ?? $request->input('SessionSid');

        $session->forceFill([
            'status' => $failed ? 'failed' : 'ended',
            'ended_at' => now(),
            'provider_session_sid' => is_string($sessionSid) && $sessionSid !== ''
                ? $sessionSid
                : $session->provider_session_sid,
            'metadata' => array_merge($session->metadata ?? [], [
                'ai_status_callback' => $request->request->all(),
            ]),
        ])->save();

        $fresh = $session->refresh();

        $this->events->dispatch($failed
            ? new AiSessionFailed($fresh, $receipt)
            : new AiSessionEnded($fresh, $receipt));

        return $fresh;
    }
}
