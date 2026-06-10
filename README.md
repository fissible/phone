# Fissible Phone

Fissible Phone is an open-source Laravel package for building Twilio-backed
business phone workflows: SMS, voice webhooks, call routing, voicemail, and
eventually AI-assisted answering.

This is a pre-alpha repository. The first goal is to define the package boundary
and build a small, dependable core before adding UI or application-specific
integrations.

## Planning

- [Scope](docs/SCOPE.md)
- [Roadmap](docs/ROADMAP.md)
- [V1 Design](docs/V1_DESIGN.md)

## Goals

- Provide Laravel-native Twilio webhook handling for SMS, MMS, voice, call
  status, recordings, and voicemail events.
- Store message threads, call records, recordings, routing decisions, and
  webhook receipts in application tables.
- Make outbound SMS and call actions queue-friendly, idempotent, and auditable.
- Expose extension points for contact lookup, CRM logging, business-hour
  routing, opt-out policy, notifications, and AI handoff.
- Keep the package usable by any Laravel application. Station support should be
  implemented as an adapter, not baked into this package.

## Non-goals

- Replacing Twilio. This package is built around Twilio primitives.
- Shipping a full hosted phone app on day one.
- Assuming Filament, Livewire, Inertia, or any specific admin UI.
- Assuming Station, Fissible CRM, or Fissible AI.
- Becoming a contact-center product like Twilio Flex.

## Planned Package Shape

The initial package is Laravel-first:

- config, migrations, routes, jobs, events, models, and services
- Twilio request validation and webhook normalization
- TwiML response builders for common routing flows
- outbound messaging services and queue jobs
- extension interfaces for contacts, inbox ownership, routing, and AI sessions

If reusable non-Laravel code becomes substantial, it can later be extracted into
a small `fissible/phone-core` PHP package. Starting in Laravel keeps the first
version grounded in the workflows that actually matter: webhooks, persistence,
queues, events, and application integration.

## Current Pre-alpha API

The current build includes package config, service-provider bindings, a `Phone`
facade, a Twilio provider adapter, and a test fake.

```php
use Fissible\Phone\Facades\Phone;

Phone::messages()
    ->to('+16615551212')
    ->body("We're on for this morning.")
    ->allowUnknownRecipient()
    ->send();
```

`send()` persists a `phone_messages` row, runs the guarded send job
synchronously, and returns the updated `PhoneMessage` model. Use `queue()` to
persist the message and dispatch the send job through Laravel's bus:

```php
Phone::messages()
    ->to('+16615551212')
    ->body('Crew is on site.')
    ->allowUnknownRecipient()
    ->queue();
```

For tests:

```php
$fake = Phone::fake();

Phone::messages()
    ->to('+16615551212')
    ->body('Crew is on site.')
    ->allowUnknownRecipient()
    ->send();

$fake->messages();
```

Outbound sends are idempotent at the message row level. The send job atomically
claims a `queued` row before calling Twilio and exits without sending if the row
already has a provider SID or is no longer sendable. Unexpected provider
failures are marked `send_unknown` instead of being blindly retried, so an
ambiguous timeout cannot double-text a customer.

Twilio sender precedence is:

1. explicit Messaging Service SID
2. configured default Messaging Service SID
3. explicit `from` number
4. configured default `from` number

By default, outbound SMS is blocked unless the recipient already has a
`phone_threads` record for the selected local number. This keeps automated sends
from accidentally texting an unresolved number. A host app can deliberately opt
in per send with `allowUnknownRecipient()` or globally:

```env
PHONE_SMS_ALLOW_UNKNOWN_RECIPIENTS=true
```

Existing threads with `opted_out_at` set are always blocked by the default
message policy.

When a Twilio status callback reaches `POST /phone/twilio/sms/status`, the
package looks up the outbound `phone_messages` row by provider SID and applies a
deterministic status progression. Lower-rank callbacks are ignored, terminal
states do not regress, carrier failure details are stored on the message, and
`Fissible\Phone\Events\MessageDeliveryUpdated` is dispatched only after the
message update is persisted.

### Webhook foundation

The package now registers stateless Twilio webhook routes under
`PHONE_ROUTE_PREFIX`, which defaults to `/phone`. The routes use only the
`phone.twilio` middleware by default, so Twilio POSTs do not pass through
Laravel's session or CSRF middleware.

Set `PHONE_WEBHOOK_BASE_URL` in production when the app is behind a TLS
terminating proxy:

```env
PHONE_WEBHOOK_BASE_URL=https://example.com
TWILIO_VALIDATE_WEBHOOKS=true
```

That value is used verbatim with the incoming request path and query string
before Twilio signature validation. This avoids the common proxy failure where
Laravel sees an internal `http://` URL but Twilio signed the public `https://`
URL.

Initial webhook routes:

- `POST /phone/twilio/sms/inbound`
- `POST /phone/twilio/sms/status`
- `POST /phone/twilio/voice/inbound`
- `POST /phone/twilio/voice/dial-status`
- `POST /phone/twilio/voice/status`
- `POST /phone/twilio/voice/recording`
- `POST /phone/twilio/voice/transcription`
- `POST /phone/twilio/ai/status`

Each request is stored in `phone_webhook_receipts` with the reconstructed public
URL, signature result, request hash, provider SID, processing status, redacted
headers, and optional payload. Invalid signatures are rejected with `403` after a
minimal forensic receipt is written. Exact webhook retries are deduplicated by a
request hash.

Inbound SMS now creates durable records:

- `phone_numbers` for local Twilio numbers
- `phone_threads` for each local/remote SMS conversation
- `phone_messages` for inbound SMS/MMS payloads

If an inbound local number is not already configured, the default resolver
creates it in the configured default scope:

```env
PHONE_DEFAULT_SCOPE_KEY=global
```

Pre-create `phone_numbers` rows when a host app needs tenant-specific scoping.
Inbound webhook scope is copied from the matched local number, not from request
context. Accepted inbound SMS/MMS webhooks dispatch
`Fissible\Phone\Events\InboundMessageReceived` after persistence.

Inbound STOP-style keywords set `phone_threads.opted_out_at`; START-style
keywords clear it. The default keyword lists are US SMS-oriented and can be
replaced by binding your own `Fissible\Phone\Contracts\OptOutPolicy`.

Voice routing is not implemented yet; the inbound voice endpoint currently
acknowledges with empty TwiML until the call router milestone lands.

## Early Milestones

1. Twilio credentials, config, and webhook signature validation.
2. Inbound SMS webhook storage and normalized message events.
3. Outbound SMS service with queued sends and status callbacks.
4. Voice webhook responses for forwarding, business hours, missed-call handling,
   and voicemail.
5. Call, recording, and voicemail persistence.
6. Contact lookup and activity logging contracts.
7. Optional UI package for shared inbox, call log, voicemail, and settings.
8. AI answering integration using Twilio Conversation Relay or Agent Connect.

## License

Fissible Phone is open-source software licensed under the MIT license.
