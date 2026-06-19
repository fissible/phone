# Stable API (1.0)

This is the public surface covered by semantic versioning from `1.0.0` onward.
Changes to anything below are breaking and require a major release. Internal
classes (processors, Twilio adapters, payload objects, default service
implementations) are **not** part of this contract and may change in minor
releases.

## Facade

`Fissible\Phone\Facades\Phone`

- `Phone::messages()` → fluent outbound message builder (`to`, `from`, `body`,
  `mediaUrl`, `metadata`, `contact`, `contactIdentity`, `allowUnknownRecipient`,
  `send`, `queue`)
- `Phone::numbers()` → number lookup
- `Phone::fake()` → test fake

## Contracts

`Fissible\Phone\Contracts\*` — each has a default binding; rebind to integrate.

`ScopeResolver`, `PhoneNumberResolver`, `ContactResolver`, `CallRouter`,
`MessagePolicy`, `OptOutPolicy`, `ActivityLogger`, `TeamNotifier`,
`AiSessionHandler`, `PhoneProvider`.

## Events

`Fissible\Phone\Events\*` — dispatched after persistence.

**SMS:** `InboundMessageReceived`, `OutboundMessageQueued`, `OutboundMessageSent`,
`OutboundMessageFailed`, `MessageDeliveryUpdated`, `ThreadOptedOut`,
`ThreadOptedIn`.

**Voice:** `InboundCallReceived`, `InboundCallContactResolved`,
`CallRouteDecided`, `CallStatusUpdated`, `RecordingStatusUpdated`,
`TranscriptionStatusUpdated`, `VoicemailReceived`.

**AI:** `AiSessionStarted`, `AiSessionEnded`, `AiSessionFailed`.

> Note: these names supersede the aspirational names in `V1_DESIGN.md`
> (`CallForwarded`/`CallMissed`/`CallCompleted`/`RecordingAvailable`/
> `VoicemailCreated`). The names listed here are canonical.

## Value objects

`RouteDecision` (incl. `ai()` + `ConversationRelayConfig`), `CallContext`,
`ContactIdentity`, `ContactLookup`, `OutboundMessage`, `ProviderMessage`,
`PhoneActivity`, `TeamNotification`, `Scope`, and the status enums under
`Fissible\Phone\Support` (`MessageStatus`, `CallStatus`, `RecordingStatus`,
`TranscriptionStatus`).

## Models and tables

`Fissible\Phone\Models\*` over the `phone_*` tables: `phone_numbers`,
`phone_threads`, `phone_messages`, `phone_calls`, `phone_recordings`,
`phone_voicemails`, `phone_transcriptions`, `phone_webhook_receipts`,
`phone_ai_sessions`. Migration column sets are stable; additive columns may
arrive in minor releases.

## Routes

The eight `/phone/twilio/*` webhook routes and their names (see
[Webhook security](webhooks-security.md)). The `route_prefix` is configurable.

## Console commands

`phone:install`, `phone:doctor`, `phone:prune`, `phone:webhook:replay`.

## Configuration

The `config/phone.php` keys documented in [Configuration](configuration.md).
