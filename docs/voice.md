# Voice

## Inbound call flow

`POST /phone/twilio/voice/inbound` is latency-sensitive — Twilio waits for TwiML.
The package keeps it DB-only and bounded:

1. validate signature, record receipt;
2. resolve the local number, create/update the `phone_calls` row;
3. ask the `CallRouter` for a `RouteDecision`;
4. persist the decision on the call;
5. return TwiML.

Slow work (contact/CRM lookup) is deferred: `ResolveInboundCallContact` is
queued after the response, storing results under `phone_calls.metadata.contact`.
`InboundCallReceived` and `CallRouteDecided` dispatch after persistence.

## Route decisions

The default `CallRouter` chooses among:

- `forward` — `<Dial>` a configured number with a dial action callback
- `voicemail` — greeting then `<Record>` (see [Voicemail](voicemail.md))
- `reject` — `<Reject>`
- `hangup` — `<Hangup>`
- `ai` — `<Connect><ConversationRelay>` (see [AI handoff](ai-handoff.md))

Default behavior: if AI is enabled and opts in, hand off to AI. Otherwise use the
number's `routing_mode` (or `default_voice.mode`); when forwarding outside
business hours, fall to `after_hours_mode`; forward when a destination is
configured, else voicemail.

```env
PHONE_FORWARD_TO=+16615559999
```

Replace routing entirely by binding `Fissible\Phone\Contracts\CallRouter`. Custom
routers must stay DB-only and fast — no external HTTP on the TwiML path.

## Business hours

Configured per number (`phone_numbers.business_hours`) or globally:

```php
'business_hours' => [
    'timezone' => 'America/Los_Angeles',
    'weekly' => [
        'monday'  => [['start' => '09:00', 'end' => '17:00']],
        'tuesday' => [['start' => '09:00', 'end' => '17:00']],
        // ...
    ],
    'holidays' => ['2026-12-25'],
],
```

Empty `weekly` means always open. Day windows may also be strings like
`09:00-17:00`; use `false` or `closed` for a closed day; overnight windows are
supported. A number's own `business_hours` overrides the global config.

## Dial fallback

The `<Dial>` includes an action callback to `POST /phone/twilio/voice/dial-status`.
On `busy`, `failed`, or `no-answer` the package can create a missed call or fall
back to voicemail. Missed-call team notifications fire only when a status callback
actually moves the call into an unanswered terminal state, so provider retries do
not double-notify.

## Call status

`POST /phone/twilio/voice/status` updates the `phone_calls` row with the same
deterministic progression as SMS: lower-rank and stale callbacks are ignored and
terminal states never regress. Provider sequence numbers are honored when present.

## Events

`InboundCallReceived`, `CallRouteDecided`, `CallStatusUpdated`.
