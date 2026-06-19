# Fissible Phone

Fissible Phone is an open-source Laravel package for building Twilio-backed
business phone workflows: SMS, voice webhooks, call routing, voicemail, webhook
security, and AI-answering handoff. It is UI-free and extendable through
contracts.

## Documentation

Full guides live in **[docs/](docs/README.md)** — installation, Twilio setup,
configuration, SMS, voice, voicemail, AI handoff, webhook security, console
commands, testing, compliance, and a production checklist.

Planning/internal docs: [Scope](docs/SCOPE.md) · [Roadmap](docs/ROADMAP.md) ·
[V1 Design](docs/V1_DESIGN.md) · [Release Policy](docs/RELEASE.md) ·
[Changelog](CHANGELOG.md)

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
    ->contact(type: 'lead', id: 123, name: 'Sam Lead')
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

Outbound sends can carry a resolved contact reference using `contact()` or
`contactIdentity()`. Contact attribution is stored on
`phone_messages.metadata.contact`; when a thread exists, it is also stored on
`phone_threads.metadata.contact` and mirrored into the thread's
`remote_display_name`, `contact_type`, and `contact_id` columns.

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

Host apps can enrich SMS threads by binding
`Fissible\Phone\Contracts\ContactResolver`. The resolver receives a lightweight
`ContactLookup` and returns a `ContactIdentity`; resolved identities are stored
under `phone_threads.metadata.contact`. The package does not create or own
contact records.

Inbound voice now creates a `phone_calls` record and returns TwiML from the
configured router. The default router uses the matched `phone_numbers` row when
it has `routing_mode=forward` and `forward_to` set, otherwise it falls back to
`PHONE_FORWARD_TO`:

```env
PHONE_FORWARD_TO=+16615559999
```

Forwarded calls include a dial action callback to
`/phone/twilio/voice/dial-status`. If no forward destination is configured, the
default router returns simple voicemail TwiML with a recording status callback
tagged as `purpose=voicemail`. Host apps can replace routing by binding
`Fissible\Phone\Contracts\CallRouter`.

Inbound voice contact lookup is deferred. The voice webhook stores the call and
queues `Fissible\Phone\Jobs\ResolveInboundCallContact` after the response so a
slow CRM lookup cannot delay TwiML. Resolved contacts are stored under
`phone_calls.metadata.contact`; resolver failures are captured under
`phone_calls.metadata.contact_resolution`.

Business-hours routing is built into the default forward mode. If no weekly
hours are configured, numbers are treated as always open. Once weekly hours are
configured, calls forward only inside those windows and use
`phone.default_voice.after_hours_mode` outside them:

```php
'business_hours' => [
    'timezone' => 'America/Los_Angeles',
    'weekly' => [
        'monday' => [['start' => '09:00', 'end' => '17:00']],
        'tuesday' => [['start' => '09:00', 'end' => '17:00']],
        'wednesday' => [['start' => '09:00', 'end' => '17:00']],
        'thursday' => [['start' => '09:00', 'end' => '17:00']],
        'friday' => [['start' => '09:00', 'end' => '17:00']],
    ],
    'holidays' => [
        '2026-12-25',
    ],
],
```

Individual `phone_numbers.business_hours` values override the global
business-hours config for that number. Day windows may also be written as
strings such as `09:00-17:00`; use `false` or `closed` for a closed day.

When Twilio reaches `POST /phone/twilio/voice/status` or the `<Dial>` action
callback at `POST /phone/twilio/voice/dial-status`, the package updates the
matching `phone_calls` row with the same deterministic progression used for SMS:
lower-rank callbacks are ignored and terminal call states do not regress. Dial
action callbacks return an empty TwiML `<Response/>` after persistence so Twilio
gets a valid voice response.

Recording callbacks create `phone_recordings`. A recording only creates a
`phone_voicemails` row when the callback is tagged with `purpose=voicemail`, so
future QA/compliance recordings can share the same recording table without being
treated as customer voicemails.

Voicemail transcription is opt-in:

```env
PHONE_TRANSCRIBE_VOICEMAILS=true
```

When enabled, voicemail TwiML includes a Twilio `transcribeCallback` pointing at
`POST /phone/twilio/voice/transcription`. Transcription callbacks create
`phone_transcriptions`; completed voicemail transcriptions also update the
matching `phone_voicemails.transcription_text`.

### Diagnostics

Run a local configuration check with:

```bash
php artisan phone:doctor
```

The command checks Twilio credentials, sender configuration, webhook base URL,
stateless webhook middleware, and default voice routing. Add `--live` to make a
single Twilio API request and verify that the configured credentials work.

### Activity Logging

Bind `Fissible\Phone\Contracts\ActivityLogger` to mirror package events into a
CRM, audit log, or host app activity stream. The default logger is a no-op.

The package currently logs structured activity entries for inbound SMS and
inbound voice after local persistence. Keep custom loggers fast in webhook
requests; for slow CRM work, prefer Laravel event listeners or queued jobs using
the persisted package events.

### Team Notifications

Bind `Fissible\Phone\Contracts\TeamNotifier` to send lightweight notifications
to a host app, Slack, email, push system, or queue. The default notifier is a
no-op. Notifications are UI-free `TeamNotification` value objects containing
the persisted package models and provider metadata.

The package currently emits team notifications for:

- inbound SMS (`sms.inbound`)
- missed inbound calls (`voice.missed`)
- new voicemails (`voicemail.received`)

Missed-call notifications are emitted only when a status callback actually moves
an inbound call into an unanswered terminal state, so duplicate provider retries
do not notify the team twice. Keep custom notifiers fast in webhook requests; if
delivery can block, hand the `TeamNotification` to a queued job.

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
