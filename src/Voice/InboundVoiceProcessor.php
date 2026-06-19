<?php

declare(strict_types=1);

namespace Fissible\Phone\Voice;

use Fissible\Phone\Contracts\ActivityLogger;
use Fissible\Phone\Contracts\CallRouter;
use Fissible\Phone\Contracts\PhoneNumberResolver;
use Fissible\Phone\Events\AiSessionStarted;
use Fissible\Phone\Events\CallRouteDecided;
use Fissible\Phone\Events\InboundCallReceived;
use Fissible\Phone\Jobs\ResolveInboundCallContact;
use Fissible\Phone\Models\PhoneAiSession;
use Fissible\Phone\Models\PhoneCall;
use Fissible\Phone\Models\PhoneNumber;
use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Support\CallStatus;
use Fissible\Phone\Twilio\TwilioInboundVoicePayload;
use Fissible\Phone\Twilio\TwilioVoiceTwiMLBuilder;
use Fissible\Phone\ValueObjects\CallContext;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\InboundVoiceResult;
use Fissible\Phone\ValueObjects\PhoneActivity;
use Fissible\Phone\ValueObjects\RouteDecision;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InboundVoiceProcessor
{
    public function __construct(
        private readonly PhoneNumberResolver $phoneNumbers,
        private readonly CallRouter $router,
        private readonly TwilioVoiceTwiMLBuilder $twiml,
        private readonly ActivityLogger $activity,
        private readonly BusDispatcher $bus,
        private readonly Dispatcher $events,
    ) {}

    public function processTwilio(Request $request, ?WebhookReceipt $receipt = null): InboundVoiceResult
    {
        $payload = TwilioInboundVoicePayload::fromRequest($request);
        $created = false;
        $decisionCreated = false;

        /** @var array{call: PhoneCall, phone_number: PhoneNumber, decision: RouteDecision} $result */
        $result = DB::transaction(function () use ($payload, $receipt, &$created, &$decisionCreated): array {
            $phoneNumber = $this->phoneNumbers->resolveForInbound($payload->to, $payload->accountSid);

            /** @var PhoneCall|null $call */
            $call = PhoneCall::query()
                ->where('provider', 'twilio')
                ->where('provider_call_sid', $payload->callSid)
                ->first();

            if (! $call instanceof PhoneCall) {
                $created = true;
                $call = PhoneCall::query()->create([
                    'scope_key' => $phoneNumber->scope_key,
                    'scope_type' => $phoneNumber->scope_type,
                    'scope_id' => $phoneNumber->scope_id,
                    'provider' => 'twilio',
                    'phone_number_id' => $phoneNumber->getKey(),
                    'webhook_receipt_id' => $receipt?->getKey(),
                    'provider_call_sid' => $payload->callSid,
                    'provider_parent_call_sid' => $payload->parentCallSid,
                    'provider_account_sid' => $payload->accountSid,
                    'provider_sequence_number' => $payload->sequenceNumber,
                    'direction' => 'inbound',
                    'from_number' => $payload->from,
                    'to_number' => $payload->to,
                    'status' => $payload->callStatus,
                    'status_rank' => CallStatus::rank($payload->callStatus),
                    'started_at' => now(),
                    'metadata' => [
                        'twilio' => $payload->raw,
                    ],
                ]);
            }

            $decision = $this->routeDecision($call, $phoneNumber, $payload, $decisionCreated);

            $aiSession = null;

            if ($decisionCreated && $decision->type === RouteDecision::AI) {
                $aiSession = $this->createAiSession($call, $phoneNumber, $decision);
            }

            return [
                'call' => $call,
                'phone_number' => $phoneNumber,
                'decision' => $decision,
                'ai_session' => $aiSession,
            ];
        });

        $call = $result['call']->refresh();
        $phoneNumber = $result['phone_number'];
        $decision = $result['decision'];
        $aiSession = $result['ai_session'];

        if ($created) {
            $this->events->dispatch(new InboundCallReceived($call, $phoneNumber, $receipt));
            $this->activity->log(new PhoneActivity(
                type: 'voice.inbound',
                channel: 'voice',
                direction: 'inbound',
                occurredAt: $call->started_at ?? now(),
                phoneNumber: $phoneNumber,
                call: $call,
                contact: ContactIdentity::anonymous($call->from_number),
                webhookReceipt: $receipt,
                metadata: [
                    'provider_call_sid' => $call->provider_call_sid,
                ],
            ));

            $this->bus->dispatchAfterResponse(
                new ResolveInboundCallContact((int) $call->getKey(), (int) $phoneNumber->getKey())
            );
        }

        if ($decisionCreated) {
            $this->events->dispatch(new CallRouteDecided($call, $phoneNumber, $decision));
        }

        if ($aiSession instanceof PhoneAiSession) {
            $this->events->dispatch(new AiSessionStarted($aiSession->refresh(), $call, $phoneNumber));
        }

        return new InboundVoiceResult(
            call: $call,
            phoneNumber: $phoneNumber,
            decision: $decision,
            twiml: $this->twiml->build($decision),
        );
    }

    private function routeDecision(
        PhoneCall $call,
        PhoneNumber $phoneNumber,
        TwilioInboundVoicePayload $payload,
        bool &$decisionCreated,
    ): RouteDecision {
        if (is_array($call->route_decision) && $call->route_decision !== []) {
            return RouteDecision::fromArray($call->route_decision);
        }

        $decisionCreated = true;
        $decision = $this->router->route(new CallContext($call, $phoneNumber, $payload));

        $call->forceFill([
            'routing_mode' => $decision->type,
            'route_decision' => $decision->toArray(),
        ])->save();

        return $decision;
    }

    private function createAiSession(
        PhoneCall $call,
        PhoneNumber $phoneNumber,
        RouteDecision $decision,
    ): PhoneAiSession {
        $config = $decision->conversationRelay;

        return PhoneAiSession::query()->create([
            'scope_key' => $phoneNumber->scope_key,
            'scope_type' => $phoneNumber->scope_type,
            'scope_id' => $phoneNumber->scope_id,
            'provider' => 'twilio',
            'phone_call_id' => $call->getKey(),
            'mode' => 'conversation_relay',
            'status' => 'started',
            'websocket_url' => $config?->websocketUrl,
            'started_at' => now(),
            'metadata' => [
                'handoff_reason' => $decision->metadata['handoff_reason'] ?? null,
                'conversation_relay' => $config?->toArray(),
            ],
        ]);
    }
}
