# V1 Design

Status: Draft

Audience: maintainers, contributors, and downstream package authors

## Summary

V1 of `fissible/phone` should be a production-usable Laravel package for a
Twilio-backed business phone line. A Laravel application should be able to
install the package, configure a Twilio number, receive and send SMS, forward
calls, apply business-hours routing, capture voicemail, track webhook delivery,
and integrate contacts, team notifications, activity logging, and AI handoff
through contracts.

V1 is not a hosted app and does not ship a mandatory inbox UI. It is the
dependable package foundation that a generic Laravel app, a Filament UI package,
or a Station adapter can build on.

## Product Promise

A developer can add `fissible/phone` to a Laravel app and get the reliable
server side of a business phone system:

- verified Twilio webhooks
- durable SMS threads
- outbound SMS with delivery tracking
- inbound voice call routing
- business-hours forwarding
- voicemail recording persistence
- operational health/replay tools
- extension points for CRM/contact lookup, notifications, audit logging, and AI

The package should be conservative, observable, and hard to accidentally
misconfigure.

## V1 Goals

- Make Twilio SMS and voice webhooks Laravel-native.
- Persist the canonical business records an app needs: numbers, threads,
  messages, calls, recordings, voicemails, webhook receipts, and optional AI
  sessions.
- Provide a small public API for sending SMS and building voice routing flows.
- Make webhook processing idempotent and replayable.
- Support single-tenant and multi-tenant Laravel apps without depending on a
  tenancy package.
- Define stable extension contracts for contact lookup, routing, notification,
  activity logging, opt-out policy, and AI handoff.
- Keep the base package UI-free.
- Provide enough documentation for a new Laravel app to configure Twilio and run
  a real number.

## V1 Non-goals

- Shared inbox UI. That belongs in `fissible/phone-filament` or app code.
- Station integration. That belongs in `fissible/station-phone` or app code.
- Browser softphone support.
- Full IVR builder.
- Twilio number purchasing and port-in automation.
- Automated 10DLC registration.
- Bundled realtime LLM/WebSocket runtime.
- Multi-provider support beyond Twilio.
- Replacing Twilio Flex or a contact-center platform.

## Target Users

### Laravel Developer

Wants to add SMS/call behavior to an existing app without building every Twilio
webhook, model, and retry path from scratch.

### Small Business App Owner

Needs a business number that can receive SMS, send reminders, forward calls,
capture voicemail, and keep a durable log.

### Package Author

Wants to build an inbox UI, CRM integration, tenant bridge, or AI answering
adapter on top of stable contracts.

## Primary Use Cases

### UC1 - Receive SMS

When a customer texts a Twilio number, the package verifies the webhook, stores
the message in a thread, records the webhook receipt, and dispatches an event
that the host app can use for notification or automation.

### UC2 - Send SMS

Application code sends an SMS through the package. The package persists an
outbound message, queues the provider send, stores the Twilio SID, and updates
delivery status from callbacks. Send jobs must be idempotent at the application
row level so queue retries do not double-text the recipient after Twilio has
already accepted a message.

### UC3 - Forward Calls

When a customer calls, the package verifies the voice webhook, records the call,
asks the configured router what to do, and returns TwiML to forward, reject,
hang up, send to voicemail, or hand off to AI.

### UC4 - Business Hours

The package supports default business-hours routing so a number can forward
during open hours and send calls to voicemail outside open hours.

### UC5 - Voicemail

If a call is unanswered, declined, or outside hours, the package returns TwiML
that records voicemail. Recording callbacks create recording and voicemail
records and dispatch events.

### UC6 - Integrate Contacts

The package resolves display identity for phone numbers through a contract. It
does not own contact records.

### UC7 - AI Handoff

The package can route a call to an AI handoff by generating TwiML that connects
Twilio Conversation Relay to a configured WebSocket URL and persists an AI
session. The package does not own the LLM runtime.

## Package Boundaries

### In This Package

- Twilio client configuration.
- Twilio webhook signature validation.
- Webhook receipts and idempotency.
- Eloquent models and migrations for phone records.
- SMS inbound, outbound, and status callbacks.
- Voice inbound, dial status, call status, and recording callbacks.
- TwiML response generation.
- Business-hours routing primitives.
- Laravel jobs, events, commands, fakes, and contracts.
- Documentation and examples.

### Outside This Package

- Admin UI.
- Tenant model ownership.
- CRM models.
- Team/user assignment UI.
- LLM provider integration.
- Conversation Relay WebSocket server.
- Billing or Twilio account provisioning.

## High-level Architecture

```text
Twilio
  |
  | HTTPS webhooks
  v
Laravel routes
  |
  v
Twilio signature middleware
  |
  v
Webhook receipt recorder
  |
  v
Webhook controller
  |
  v
Normalizer -> Processor -> Models -> Events
                         |
                         v
                  Extension contracts

Application code
  |
  v
Phone facade/service -> Jobs -> Twilio provider adapter -> Twilio REST API
```

## Composer Dependencies

Runtime:

- `php`: `^8.2`
- `illuminate/support`: `^11.0|^12.0|^13.0`
- `illuminate/database`: same supported range as Laravel components
- `illuminate/http`: same supported range as Laravel components
- `illuminate/queue`: same supported range as Laravel components
- `twilio/sdk`: `^8.0`

The CI matrix should test only Laravel versions that are installable at release
time. Future version constraints may be declared ahead of a release, but they do
not count as a release gate until the dependency is available to CI.

Development:

- `orchestra/testbench`
- `pestphp/pest`
- `pestphp/pest-plugin-laravel`
- `laravel/pint`

## Configuration

Publishable config file: `config/phone.php`.

Required sections:

```php
return [
    'provider' => env('PHONE_PROVIDER', 'twilio'),

    'route_prefix' => env('PHONE_ROUTE_PREFIX', 'phone'),

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'default_from' => env('TWILIO_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'validate_webhooks' => env('TWILIO_VALIDATE_WEBHOOKS', true),
    ],

    'default_voice' => [
        'mode' => 'forward',
        'forward_to' => env('PHONE_FORWARD_TO'),
        'timeout' => 20,
        'after_hours_mode' => 'voicemail',
    ],

    'business_hours' => [
        'timezone' => env('PHONE_TIMEZONE', config('app.timezone')),
        'weekly' => [],
        'holidays' => [],
    ],

    'webhooks' => [
        'base_url' => env('PHONE_WEBHOOK_BASE_URL'),
        'middleware' => ['phone.twilio'],
        'store_raw_payloads' => true,
        'redact' => [],
        'replay_enabled' => true,
    ],

    'retention' => [
        'webhook_receipts_days' => 90,
        'raw_payload_days' => 30,
    ],
];
```

Config must be usable without publishing. Published config is for customization.

`webhooks.base_url` is required for reliable production signature validation
behind TLS-terminating proxies. When set, the package must validate Twilio
signatures against that public URL plus the route path and query string, not the
internal URL observed by the Laravel request. Example:
`https://example.com`.

Apps should still configure Laravel trusted proxies correctly, but webhook
signature validation must not depend on proxy headers being perfect.
Generated callback URLs for outbound SMS status, `<Dial>` actions, recordings,
and AI status should also use this base URL when configured.

## Public API Sketch

```php
use Fissible\Phone\Facades\Phone;

Phone::messages()
    ->to('+16615551212')
    ->body("We're on for this morning.")
    ->send();

Phone::messages()
    ->from('+16615550100')
    ->to('+16615551212')
    ->body('Crew is on site.')
    ->queue();

Phone::numbers()->findByNumber('+16615550100');

Phone::calls()->routeUsing($callContext);
```

The exact fluent API can change before v1, but v1 must expose a small,
documented service interface that does not require direct use of Twilio SDK
objects.

## Routes

Default route prefix: `/phone`.

Routes should be publishable/disableable for apps that want custom route
registration.

| Method | Path | Name | Purpose |
| --- | --- | --- | --- |
| POST | `/phone/twilio/sms/inbound` | `phone.twilio.sms.inbound` | Inbound SMS/MMS webhook |
| POST | `/phone/twilio/sms/status` | `phone.twilio.sms.status` | Outbound message status callback |
| POST | `/phone/twilio/voice/inbound` | `phone.twilio.voice.inbound` | Inbound call TwiML webhook |
| POST | `/phone/twilio/voice/dial-status` | `phone.twilio.voice.dial-status` | Dial action callback |
| POST | `/phone/twilio/voice/status` | `phone.twilio.voice.status` | Call status callback |
| POST | `/phone/twilio/voice/recording` | `phone.twilio.voice.recording` | Recording callback |
| POST | `/phone/twilio/voice/transcription` | `phone.twilio.voice.transcription` | Optional transcription callback |
| POST | `/phone/twilio/ai/status` | `phone.twilio.ai.status` | Optional AI handoff status callback |

Route middleware:

- dedicated stateless package middleware, default `phone.twilio`
- no session middleware
- no CSRF middleware
- Twilio signature validation and receipt middleware
- Optional app-provided middleware.

Twilio routes must not be registered in Laravel's default `web` middleware group
unless the host app explicitly excludes these routes from CSRF verification.
The package default should be stateless so a standard Twilio POST cannot fail
with a `419` CSRF response.
If a host app overrides route middleware, the package docs must warn that adding
session or CSRF middleware is unsupported for Twilio webhook routes.

## Database Design

All tables use package-owned names with `phone_` prefix.

### Common Columns

Most business tables should include:

- `id`
- `scope_key` string, default `global`
- `scope_type` nullable string
- `scope_id` nullable string
- `provider` string, default `twilio`
- `metadata` JSON nullable
- `created_at`
- `updated_at`

Scope columns allow a multi-tenant app to isolate records without making the
package depend on tenant models. `scope_key` is the non-null value used for
indexes and uniqueness; `scope_type` and `scope_id` are descriptive metadata for
host app integrations.

Provider SID unique indexes should be plain composite unique indexes. Do not use
filtered or partial `WHERE NOT NULL` indexes in package migrations; MySQL does
not support them, and the supported database engines allow multiple NULL values
in a unique composite index while still enforcing uniqueness for real SIDs.

### `phone_numbers`

Represents a local business number.

Key columns:

- `phone_number` string, normalized E.164
- `friendly_name` nullable string
- `provider_account_sid` nullable string
- `provider_number_sid` nullable string
- `messaging_service_sid` nullable string
- `capabilities` JSON
- `voice_enabled` boolean
- `sms_enabled` boolean
- `mms_enabled` boolean
- `routing_mode` string
- `forward_to` nullable string
- `business_hours` JSON nullable
- `voicemail_greeting` nullable text
- `status` string

Indexes:

- unique on `scope_key`, `phone_number`
- index on `provider_number_sid`
- index on `status`

### `phone_threads`

Groups messages with an external participant.

Key columns:

- `phone_number_id` foreign key
- `local_number` string
- `remote_number` string
- `remote_display_name` nullable string
- `contact_type` nullable string
- `contact_id` nullable string
- `assigned_to_type` nullable string
- `assigned_to_id` nullable string
- `last_message_at` nullable timestamp
- `last_inbound_message_at` nullable timestamp
- `last_outbound_message_at` nullable timestamp
- `unread_count` unsigned integer default `0`
- `opted_out_at` nullable timestamp
- `archived_at` nullable timestamp

Indexes:

- unique on `scope_key`, `phone_number_id`, `remote_number`
- index on `last_message_at`
- index on `contact_type`, `contact_id`

### `phone_messages`

Stores inbound and outbound SMS/MMS.

Key columns:

- `phone_thread_id` foreign key nullable until thread is resolved
- `phone_number_id` foreign key nullable
- `provider_message_sid` nullable string
- `provider_account_sid` nullable string
- `direction` string: `inbound`, `outbound`
- `from_number` string
- `to_number` string
- `body` text nullable
- `media` JSON nullable
- `num_segments` unsigned integer nullable
- `status` string
- `status_rank` unsigned integer default `0`
- `error_code` nullable string
- `error_message` nullable string
- `queued_at` nullable timestamp
- `sent_at` nullable timestamp
- `delivered_at` nullable timestamp
- `failed_at` nullable timestamp
- `received_at` nullable timestamp

Indexes:

- unique on `provider`, `provider_message_sid`
- index on `phone_thread_id`, `created_at`
- index on `status`

### `phone_calls`

Stores inbound and outbound calls.

Key columns:

- `phone_number_id` foreign key nullable
- `provider_call_sid` nullable string
- `provider_parent_call_sid` nullable string
- `provider_account_sid` nullable string
- `provider_sequence_number` nullable integer
- `direction` string: `inbound`, `outbound`
- `from_number` string
- `to_number` string
- `status` string
- `status_rank` unsigned integer default `0`
- `routing_mode` nullable string
- `route_decision` JSON nullable
- `answered_by` nullable string
- `duration_seconds` nullable integer
- `started_at` nullable timestamp
- `answered_at` nullable timestamp
- `ended_at` nullable timestamp

Indexes:

- unique on `provider`, `provider_call_sid`
- index on `phone_number_id`, `created_at`
- index on `status`

### `phone_recordings`

Stores Twilio call recordings.

Key columns:

- `phone_call_id` foreign key nullable
- `provider_recording_sid` nullable string
- `provider_call_sid` nullable string
- `purpose` string nullable: `voicemail`, `call_recording`, `qa`, `unknown`
- `status` string
- `recording_url` nullable string
- `duration_seconds` nullable integer
- `content_type` nullable string
- `available_at` nullable timestamp
- `transcription_status` nullable string
- `transcription_text` nullable long text

Indexes:

- unique on `provider`, `provider_recording_sid`
- index on `provider_call_sid`

### `phone_voicemails`

Business-level voicemail record.

Key columns:

- `phone_call_id` foreign key nullable
- `phone_recording_id` foreign key nullable
- `phone_thread_id` foreign key nullable
- `from_number` string
- `to_number` string
- `status` string: `new`, `notified`, `reviewed`, `archived`
- `transcript` nullable long text
- `summary` nullable text
- `notified_at` nullable timestamp
- `reviewed_at` nullable timestamp

Indexes:

- index on `status`
- index on `created_at`

### `phone_webhook_receipts`

Durable receipt for every accepted Twilio webhook.

Key columns:

- `provider` string
- `event_type` string
- `provider_sid` nullable string
- `request_method` string
- `request_url` text
- `source_ip` nullable string
- `signature_valid` boolean
- `headers` JSON nullable
- `payload` JSON nullable
- `payload_hash` string
- `processing_status` string: `pending`, `processed`, `failed`, `ignored`
- `processed_at` nullable timestamp
- `failed_at` nullable timestamp
- `error_class` nullable string
- `error_message` nullable text
- `replay_count` unsigned integer default `0`

Indexes:

- unique on `provider`, `event_type`, `payload_hash`
- index on `provider_sid`
- index on `processing_status`

### `phone_ai_sessions`

Optional record for AI handoff and post-call AI work.

Key columns:

- `phone_call_id` foreign key nullable
- `provider_session_sid` nullable string
- `mode` string: `conversation_relay`, `post_call`, `external`
- `status` string
- `websocket_url` nullable text
- `started_at` nullable timestamp
- `ended_at` nullable timestamp
- `transcript` nullable long text
- `summary` nullable text
- `handoff_reason` nullable string
- `metadata` JSON nullable

Indexes:

- index on `phone_call_id`
- index on `status`

## State Machines

### Message Status

Internal statuses:

- `draft`
- `queued`
- `sending`
- `send_unknown`
- `sent`
- `delivered`
- `undelivered`
- `failed`
- `received`
- `ignored`

Rules:

- Inbound messages are stored as `received`.
- Outbound messages start as `queued`.
- Twilio status callbacks update outbound messages.
- Message callbacks may arrive out of order and do not provide a reliable
  sequence number. Updates must use a deterministic status precedence rule and
  atomic writes.

Terminal statuses:

- `delivered`
- `undelivered`
- `failed`
- `ignored`

Recommended outbound precedence:

1. `draft`
2. `queued`
3. `sending`
4. `send_unknown`
5. `sent`
6. `delivered`
7. terminal failure: `undelivered`, `failed`, `ignored`

Status callback processors must update records conditionally so stale callbacks
cannot regress status. A typical implementation is an atomic update that only
applies when the new rank is greater than the current rank and the current
status is not terminal, except terminal statuses may set final error metadata.

### Call Status

Internal statuses:

- `initiated`
- `ringing`
- `in_progress`
- `completed`
- `busy`
- `failed`
- `no_answer`
- `canceled`
- `missed`

Rules:

- Inbound voice webhook creates or updates the call.
- Dial status callback updates route outcome.
- Call status callback updates provider lifecycle.
- Package should preserve raw provider status in metadata.
- When Twilio supplies a callback sequence number, store it and ignore older
  sequence numbers. If no sequence number is available, apply a conservative
  status precedence rule and never overwrite terminal call metadata with an
  older-looking callback.

### Voicemail Status

Internal statuses:

- `new`
- `notified`
- `reviewed`
- `archived`

## Webhook Processing Rules

All Twilio webhooks must follow this sequence:

1. Build the exact public URL used for signature validation.
2. Validate `X-Twilio-Signature` unless disabled for local/test environments.
3. Store a `phone_webhook_receipts` record.
4. Reject invalid signatures after storing a minimal forensic receipt.
5. Detect duplicate payloads.
6. Normalize the payload to a typed object.
7. Process idempotently.
8. Dispatch domain events only after persistence succeeds.
9. Return a Twilio-compatible response quickly.

Long work should be queued. Voice webhooks must return TwiML synchronously.

Public URL construction must prefer `phone.webhooks.base_url` when configured.
The validator should combine that base URL with the route path and query string
from the current request. This avoids false signature failures when Laravel sees
an internal host or `http` scheme behind a proxy but Twilio signed the public
`https` URL.

Invalid-signature receipts should store only minimal metadata by default:
provider, event type if known, request URL, payload hash, source IP if
available, headers after redaction, `signature_valid=false`, and
`processing_status=ignored`. They should not dispatch domain events or be
replayable.

## Twilio Provider Requirements

The Twilio provider adapter owns:

- creating outbound messages
- optionally creating outbound calls
- mapping Twilio message statuses to internal statuses
- mapping Twilio call statuses to internal statuses
- validating webhook signatures through Twilio SDK helpers
- building TwiML responses
- normalizing Twilio webhook payloads

The provider adapter must retain raw Twilio SIDs and payload fields needed for
debugging.

## SMS Design

### Inbound Flow

1. Twilio posts to `/phone/twilio/sms/inbound`.
2. Signature middleware validates request.
3. Receipt recorder stores request.
4. Controller normalizes fields such as `MessageSid`, `AccountSid`, `From`,
   `To`, `Body`, `NumMedia`, and media URLs.
5. Number resolver finds or creates the local `phone_numbers` record if
   configured to do so.
6. Inbound scope is taken from the matched phone number's `scope_key`; request
   context must not be used to infer tenant/scope for webhooks.
7. Thread resolver finds or creates the thread.
8. Contact resolver enriches thread identity.
9. Message is stored as `received`.
10. Opt-out policy evaluates message body.
11. Events are dispatched.
12. Response returns `200 OK`.

### Outbound Flow

1. Application calls the message service.
2. Message is validated against opt-out and number policy.
3. `phone_messages` row is created as `queued`.
4. `SendOutboundMessage` atomically claims the row by moving it from `queued` to
   `sending`.
5. Before calling Twilio, the job reloads the row and exits if a
   `provider_message_sid` already exists or the status is no longer sendable.
6. The job sends through Twilio using the configured Messaging Service when
   available.
7. Twilio SID is saved.
8. Message transitions to `sent` or `failed`.
9. Status callback updates final delivery state.

Twilio message creation should be treated as non-idempotent. If the provider
request times out or fails after the HTTP request may have reached Twilio, the
job must not blindly retry and risk a duplicate text. Mark the message
`send_unknown`, dispatch an operational event, and require callback recovery,
manual replay, or an explicit force retry.

### Outbound API Requirements

The outbound API must support:

- messaging service SID as the preferred sender path
- explicit `from` number
- default `from` number
- body
- media URLs
- metadata
- synchronous send
- queued send
- status callback URL generation

Sender precedence:

1. explicit messaging service SID
2. configured default messaging service SID
3. explicit `from` number
4. configured default `from` number

The docs should recommend Messaging Services for A2P/10DLC usage because the
campaign, sender pool, sticky-sender behavior, and delivery callbacks are managed
there.

## Voice Design

### Inbound Call Flow

1. Twilio posts to `/phone/twilio/voice/inbound`.
2. Signature middleware validates request.
3. Receipt recorder stores request.
4. Controller normalizes `CallSid`, `AccountSid`, `From`, `To`, and call
   status fields.
5. Number resolver finds local number.
6. Inbound scope is taken from the matched phone number's `scope_key`.
7. Call is created or updated.
8. Call router returns a route decision.
9. Decision is persisted on the call.
10. TwiML response is returned.

The inbound voice path is latency-sensitive because Twilio is waiting for TwiML.
It must avoid slow or external I/O. Number lookup, route lookup, call
persistence, and TwiML generation should be DB-only and bounded. Contact
enrichment, CRM writes, notifications, AI preparation beyond choosing a
preconfigured handoff URL, and other slow work must be deferred to events/jobs
after TwiML is returned.

### Route Decision Types

- `forward`: `<Dial>` a configured number.
- `voicemail`: `<Say>` or `<Play>`, then `<Record>`.
- `reject`: `<Reject>`.
- `hangup`: `<Hangup>`.
- `ai`: `<Connect><ConversationRelay>`.

### Dial Callback Flow

The `<Dial>` response should include an action URL. The callback lets the
package decide whether to end the call, create a missed call, or fall back to
voicemail after `busy`, `failed`, or `no-answer`.

### Recording Callback Flow

The `<Record>` response should include `recordingStatusCallback`. When Twilio
reports that a recording is available, the package creates or updates a
recording record. It creates a voicemail only when the recording purpose is
`voicemail`.

The recording purpose must be threaded through the recording callback, either as
a callback query parameter generated by the route decision or as metadata
derived from the persisted call route decision. A forwarded-call QA recording
must not become a voicemail by accident.

## Routing Design

### Contracts

```php
interface CallRouter
{
    public function route(CallContext $call): RouteDecision;
}
```

Default router behavior:

- If number has custom routing config, use it.
- If business hours are configured and current time is closed, route to
  after-hours mode.
- If open and `forward_to` is configured, forward.
- Otherwise route to voicemail.

The default router must be safe for the synchronous voice webhook path. It should
read only local configuration/model data and must not perform external HTTP
calls. Custom routers should document the same expectation.

### Business Hours

Business-hours value object should support:

- timezone
- weekly windows by day
- closed days
- date-specific holidays
- overnight windows

V1 should avoid a complex scheduling UI. Config and model JSON are enough.

## AI Handoff Design

V1 should make AI answering possible without bundling an LLM runtime.

Included in core:

- `ai` route decision type
- Conversation Relay TwiML builder
- `phone_ai_sessions` model
- `AiSessionHandler` contract
- events for AI session started, updated, ended, and failed
- documentation for how an app or companion package supplies Conversation Relay
  settings

Not included in core:

- OpenAI/Anthropic SDK usage
- prompt management
- tool execution engine
- long-running WebSocket server
- speech transcript UI

The base package should own call routing and persistence. A companion package or
host app should own the realtime AI runtime.

`AiSessionHandler` should be able to provide the full Conversation Relay
attribute set required by the TwiML builder, not only a WebSocket URL. At
minimum this includes:

- WebSocket URL
- voice
- language
- welcome greeting
- interruption/barge-in behavior when supported
- provider/session metadata
- optional authentication material or signed connection parameters

## Contracts

### `ScopeResolver`

Returns the current package scope for outbound or app-initiated operations.

Default: returns the `global` scope key with null type/id metadata.

Inbound webhook scope must come from the matched `PhoneNumber` record, not this
resolver. Webhooks have no authenticated user/session context, and the `To`
number is the stable routing signal.

### `PhoneNumberResolver`

Maps an inbound local number to a `PhoneNumber` model.

Default: query by normalized E.164 number.

### `ContactResolver`

Maps a remote phone number to a display identity.

Default: returns an anonymous identity.

### `CallRouter`

Returns a `RouteDecision` for inbound calls.

Default: business-hours forward/voicemail router.

### `MessagePolicy`

Determines whether outbound messages can be sent.

Default: block opted-out threads; allow otherwise.

### `OptOutPolicy`

Detects and applies STOP/START-style opt-out behavior.

Default: US SMS keywords.

### `ActivityLogger`

Lets host apps mirror phone events into a CRM or audit log.

Default: no-op.

### `TeamNotifier`

Lets host apps notify people about inbound SMS, missed calls, and voicemail.

Default: no-op.

### `AiSessionHandler`

Supplies AI handoff settings and receives AI lifecycle events.

Default: disabled.

## Events

V1 dispatches events after database persistence. The **shipped, canonical**
names are below (see `docs/stable-api.md`). Earlier drafts of this section used
aspirational names (`CallForwarded`, `CallMissed`, `CallCompleted`,
`RecordingAvailable`, `VoicemailCreated`); those were consolidated into the
status-update events actually shipped.

SMS:

- `InboundMessageReceived`
- `OutboundMessageQueued`
- `OutboundMessageSent`
- `OutboundMessageFailed`
- `MessageDeliveryUpdated`
- `ThreadOptedOut`
- `ThreadOptedIn`

Voice:

- `InboundCallReceived`
- `InboundCallContactResolved`
- `CallRouteDecided`
- `CallStatusUpdated` (covers forwarded/missed/completed lifecycle)
- `RecordingStatusUpdated`
- `TranscriptionStatusUpdated`
- `VoicemailReceived`

AI:

- `AiSessionStarted`
- `AiSessionEnded`
- `AiSessionFailed`

## Jobs

- `SendOutboundMessage`
- `ProcessMessageStatusCallback`
- `ProcessCallStatusCallback`
- `ProcessRecordingCallback`
- `NotifyTeamOfInboundMessage`
- `NotifyTeamOfMissedCall`
- `NotifyTeamOfVoicemail`
- `PruneWebhookReceipts`

Voice TwiML generation should not depend on queued jobs.

`SendOutboundMessage` requirements:

- claim a queued row atomically before calling Twilio
- exit without sending if `provider_message_sid` is already present
- exit without sending if the status is not sendable
- save the provider SID immediately after a successful provider response
- avoid automatic retries after uncertain provider timeouts
- mark uncertain sends as `send_unknown` for operator review or callback
  reconciliation

## Console Commands

### `phone:install`

Publishes config and migrations.

### `phone:doctor`

Checks:

- config values are present
- Twilio credentials are syntactically present
- route URLs are generated
- webhook validation is enabled outside local/test
- queue connection is configured
- database tables exist

Optional flag: `--live`.

With `--live`, the command may make a single Twilio API request to verify that
credentials work. The default command must remain offline-safe and should not
contact Twilio.

### `phone:webhook:replay {receipt}`

Reprocesses a failed webhook receipt.

### `phone:prune`

Prunes old webhook receipts and raw payloads based on retention config.

## Security Requirements

- Validate Twilio signatures by default.
- Never disable validation in production unless the host app explicitly opts in.
- Validate signatures against the configured public webhook base URL when set.
- Store minimal rejected-request receipts for invalid signatures.
- Register Twilio webhook routes in a stateless, CSRF-free middleware group.
- Avoid logging auth tokens.
- Store raw payloads only when configured.
- Provide payload redaction hooks.
- Store media URLs but do not download media by default in v1.
- Document Twilio media authentication and retention implications.
- Keep webhook replay permission to console/app code only.

## Compliance Requirements

The package should provide hooks and docs, not legal guarantees.

V1 docs must cover:

- 10DLC registration responsibility.
- Consent requirements for outbound SMS.
- STOP/START behavior.
- Message body retention considerations.
- MMS/recording media lifecycle: Twilio media URLs may require authentication
  and may not be retained forever. Apps that need long-term media retention must
  fetch and store media in their own storage before provider retention expires.
- Recording consent considerations.
- How to disable raw payload storage.

## Testing Strategy

### Unit Tests

- status mappers
- status precedence rules
- phone number normalization
- business-hours calculations
- route decisions
- opt-out policy
- TwiML builders

### Feature Tests

- inbound SMS webhook
- SMS status callback
- outbound SMS queue job with fake provider
- outbound SMS job does not double-send when retried after SID is saved
- outbound SMS uncertain timeout becomes `send_unknown`, not automatic resend
- inbound voice webhook forwarding
- dial status fallback to voicemail
- recording callback to voicemail only when purpose is `voicemail`
- invalid Twilio signature rejection
- invalid Twilio signature creates minimal rejected receipt
- signature validation uses configured public base URL behind proxy
- webhook routes do not require CSRF/session middleware
- duplicate webhook idempotency
- out-of-order status callbacks do not regress message or call status
- webhook replay

### Integration Test Shape

No tests should require real Twilio credentials. Provider fakes should capture
outbound API calls and return realistic SIDs.

## Documentation Requirements

V1 docs:

- installation
- configuration
- Twilio console setup
- inbound SMS setup
- outbound SMS usage
- voice forwarding setup
- voicemail setup
- business-hours setup
- webhook security
- public webhook base URL and proxy setup
- stateless webhook middleware and CSRF avoidance
- local development with public tunnel
- testing with provider fake
- extension contracts
- AI handoff overview
- 10DLC and Messaging Service guidance
- media retention guidance
- production checklist

## V1 Acceptance Criteria

V1 is complete when:

- Package installs in a fresh Laravel 11, 12, or 13 app.
- Config and migrations publish successfully.
- Twilio webhooks are signature-validated by default.
- Signature validation works behind a proxy using configured public base URL.
- Twilio routes are stateless and do not require CSRF tokens.
- Inbound SMS creates durable threads and messages.
- Outbound SMS can be sent synchronously or queued.
- Outbound send jobs guard against duplicate sends on retry.
- Outbound message status callbacks update records idempotently.
- Out-of-order status callbacks cannot regress records.
- Inbound calls can be forwarded during business hours.
- Calls can fall back to voicemail.
- Recording callbacks create voicemail records only for voicemail-purpose
  recordings.
- Duplicate webhooks do not duplicate business records.
- Failed webhook processing can be inspected and replayed.
- Contacts, routing, notifications, activity logging, and AI handoff are
  extendable through contracts.
- No Station, Filament, Livewire, Inertia, or tenancy package is required.
- Tests cover the core flows.
- Documentation is sufficient to configure a real Twilio number.

## Issue-ready Work Breakdown

### Epic V1-01 - Package Foundation

Outcome: The package has a reliable development and CI base.

Issues:

- `V1-01-01` Add Testbench/Pest test harness.
- `V1-01-02` Add GitHub Actions for Composer validation and tests.
- `V1-01-03` Add Pint configuration.
- `V1-01-04` Add publishable config and service-provider bootstrapping.
- `V1-01-05` Add package install docs.

Acceptance:

- CI passes on pull requests.
- Package boots in a Testbench app.
- Config can be published.

### Epic V1-02 - Provider Configuration

Outcome: Application code can resolve a configured phone service and Twilio
adapter.

Issues:

- `V1-02-01` Add `PhoneManager` and container bindings.
- `V1-02-02` Add Twilio client factory.
- `V1-02-03` Add provider fake for tests.
- `V1-02-04` Add actionable config exceptions.
- `V1-02-05` Add `Phone` facade.

Acceptance:

- Missing credentials produce clear exceptions.
- Tests can fake provider calls.
- Public API never exposes Twilio SDK objects by default.

### Epic V1-03 - Webhook Foundation

Outcome: All webhook flows share security, receipts, and idempotency.

Issues:

- `V1-03-01` Add Twilio signature validation middleware.
- `V1-03-02` Add webhook receipt migration/model.
- `V1-03-03` Add receipt recorder service.
- `V1-03-04` Add payload redaction config.
- `V1-03-05` Add webhook replay service.
- `V1-03-06` Add tests for invalid signature and duplicate delivery.
- `V1-03-07` Add public webhook base URL support for proxy-safe validation.
- `V1-03-08` Add stateless CSRF-free webhook route middleware.
- `V1-03-09` Store minimal receipts for invalid-signature requests.

Acceptance:

- Invalid signatures are rejected.
- Every accepted webhook has a receipt.
- Invalid signatures have a minimal forensic receipt.
- Duplicate payloads do not duplicate domain records.
- Proxy/TLS termination does not break signature validation when base URL is
  configured.

### Epic V1-04 - Core Models

Outcome: Durable phone records exist with stable query surfaces.

Issues:

- `V1-04-01` Add `phone_numbers` migration/model.
- `V1-04-02` Add `phone_threads` migration/model.
- `V1-04-03` Add `phone_messages` migration/model.
- `V1-04-04` Add `phone_calls` migration/model.
- `V1-04-05` Add `phone_recordings` migration/model.
- `V1-04-06` Add `phone_voicemails` migration/model.
- `V1-04-07` Add `phone_ai_sessions` migration/model.

Acceptance:

- Migrations run cleanly.
- Models expose relationships.
- Indexes support expected lookup paths.

### Epic V1-05 - SMS

Outcome: SMS inbound/outbound flows are production-usable.

Issues:

- `V1-05-01` Add inbound SMS route/controller.
- `V1-05-02` Add SMS payload normalizer.
- `V1-05-03` Add thread resolver.
- `V1-05-04` Add inbound message processor.
- `V1-05-05` Add outbound message service.
- `V1-05-06` Add queued outbound send job.
- `V1-05-07` Add message status callback route/controller.
- `V1-05-08` Add opt-out policy.
- `V1-05-09` Add SMS events.
- `V1-05-10` Add SMS docs.
- `V1-05-11` Add outbound send idempotency guard and uncertain-send handling.
- `V1-05-12` Add message status precedence and atomic update rules.
- `V1-05-13` Add Messaging Service sender precedence.

Acceptance:

- Inbound SMS stores one message per Twilio message SID.
- Outbound SMS can be sent with fake provider in tests.
- Retried jobs do not double-send after Twilio accepted a message.
- Provider timeouts after possible send are marked `send_unknown`.
- Status callbacks update message records.
- Out-of-order status callbacks do not regress message state.
- Opted-out threads block outbound sends by default.

### Epic V1-06 - Voice

Outcome: A Twilio number can act as a simple business line.

Issues:

- `V1-06-01` Add inbound voice route/controller.
- `V1-06-02` Add call payload normalizer.
- `V1-06-03` Add call router contract and default router.
- `V1-06-04` Add TwiML builder for forward/reject/hangup.
- `V1-06-05` Add dial status callback.
- `V1-06-06` Add call status callback.
- `V1-06-07` Add missed-call event.
- `V1-06-08` Add voice docs.
- `V1-06-09` Enforce synchronous voice path latency boundaries.
- `V1-06-10` Use provider callback sequence numbers where available.

Acceptance:

- Inbound call returns valid TwiML.
- Forwarded calls include a dial action callback.
- Busy/no-answer can fall back to voicemail.
- Call status updates are persisted.
- Slow contact/CRM work is deferred outside the TwiML response path.

### Epic V1-07 - Business Hours And Voicemail

Outcome: The default router supports expected business phone behavior.

Issues:

- `V1-07-01` Add business-hours value objects.
- `V1-07-02` Add business-hours route decision logic.
- `V1-07-03` Add voicemail TwiML builder.
- `V1-07-04` Add recording callback route/controller.
- `V1-07-05` Add voicemail processor.
- `V1-07-06` Add voicemail events.
- `V1-07-07` Add voicemail docs.
- `V1-07-08` Thread recording purpose through callback processing.

Acceptance:

- Closed-hours calls route to voicemail by default.
- Voicemail-purpose recording callbacks create voicemail records.
- Non-voicemail recordings do not create voicemail records.
- Applications can listen for `VoicemailCreated`.

### Epic V1-08 - Extension Contracts

Outcome: Host apps can integrate without forking.

Issues:

- `V1-08-01` Add `ScopeResolver`.
- `V1-08-02` Add `PhoneNumberResolver`.
- `V1-08-03` Add `ContactResolver`.
- `V1-08-04` Add `MessagePolicy`.
- `V1-08-05` Add `ActivityLogger`.
- `V1-08-06` Add `TeamNotifier`.
- `V1-08-07` Add `AiSessionHandler`.
- `V1-08-08` Add contract docs and examples.

Acceptance:

- Every contract has a default implementation.
- Station can map tenant/contact/activity concepts through contracts.
- A basic Laravel app can ignore all contracts and still work.

### Epic V1-09 - AI Handoff

Outcome: The core package can hand a call to an external realtime AI service.

Issues:

- `V1-09-01` Add `ai` route decision type.
- `V1-09-02` Add Conversation Relay TwiML builder.
- `V1-09-03` Add AI session lifecycle service.
- `V1-09-04` Add AI status callback route.
- `V1-09-05` Add AI session events.
- `V1-09-06` Add AI handoff docs.
- `V1-09-07` Add full Conversation Relay attribute contract.

Acceptance:

- Router can return an AI handoff decision.
- Package emits TwiML with configured WebSocket URL.
- Package supports voice, language, greeting, and auth/session attributes.
- AI session records are persisted.
- No LLM provider dependency is required.

### Epic V1-10 - Operations

Outcome: Operators can inspect, replay, and maintain the package.

Issues:

- `V1-10-01` Add `phone:doctor`.
- `V1-10-02` Add `phone:webhook:replay`.
- `V1-10-03` Add `phone:prune`.
- `V1-10-04` Add retention configuration.
- `V1-10-05` Add production checklist.
- `V1-10-06` Add compliance docs.
- `V1-10-07` Add optional `phone:doctor --live` Twilio credential check.

Acceptance:

- Doctor command catches common misconfiguration.
- Doctor command can optionally verify live Twilio credentials.
- Failed webhook receipts can be replayed.
- Old raw payloads can be pruned.

### Epic V1-11 - Documentation And Examples

Outcome: Developers can use the package without reading internals.

Issues:

- `V1-11-01` Write quickstart.
- `V1-11-02` Write Twilio console setup guide.
- `V1-11-03` Write SMS guide.
- `V1-11-04` Write voice guide.
- `V1-11-05` Write voicemail guide.
- `V1-11-06` Write business-hours guide.
- `V1-11-07` Write testing/fakes guide.
- `V1-11-08` Add example appointment reminder.
- `V1-11-09` Write proxy/base URL and CSRF-free webhook setup guide.
- `V1-11-10` Write 10DLC/Messaging Service guidance.
- `V1-11-11` Write media retention guidance.

Acceptance:

- A fresh Laravel app can receive SMS by following docs.
- A fresh Laravel app can forward calls by following docs.
- Example code passes tests.

## Suggested Milestone Order

1. V1-01 Package Foundation
2. V1-02 Provider Configuration
3. V1-03 Webhook Foundation
4. V1-04 Core Models
5. V1-05 SMS
6. V1-06 Voice
7. V1-07 Business Hours And Voicemail
8. V1-08 Extension Contracts
9. V1-09 AI Handoff
10. V1-10 Operations
11. V1-11 Documentation And Examples

## Release Gates

### Alpha

- Config, migrations, webhook validation, receipts, inbound SMS, outbound SMS.

### Beta

- Voice forwarding, call status, business hours, voicemail, extension
  contracts.

### Release Candidate

- AI handoff, operations commands, full docs, production checklist, compatibility
  tests.

### V1.0

- Stable public contracts.
- Stable migrations.
- Passing CI across supported Laravel versions.
- Documentation complete.

## References

- Twilio request validation:
  https://www.twilio.com/docs/usage/security#validating-requests
- Twilio outbound message status callbacks:
  https://www.twilio.com/docs/messaging/guides/track-outbound-message-status
- Twilio `<Dial>`:
  https://www.twilio.com/docs/voice/twiml/dial
- Twilio `<Record>`:
  https://www.twilio.com/docs/voice/twiml/record
- Twilio Conversation Relay:
  https://www.twilio.com/docs/voice/conversationrelay
