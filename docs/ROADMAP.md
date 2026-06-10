# Roadmap

This document scopes the work needed to turn `fissible/phone` from a package
shell into a usable Laravel foundation for Twilio-backed SMS and voice.

## Working Assumptions

- The base package is Laravel-first and UI-free.
- Twilio is the first supported provider.
- Provider-specific details stay behind contracts where the boundary is clear.
- Multi-tenant apps are supported through a nullable application scope, not a
  hard dependency on any tenancy package.
- Station integration is a separate adapter, not part of this repository.
- A Filament inbox can be a companion package after the core is useful.

## Architecture Decisions

### Application Scope

Core tables should include nullable scope columns so single-tenant apps can use
the package without setup, while multi-tenant apps can isolate records.

Recommended shape:

- `scope_type` nullable string
- `scope_id` nullable string

The package should expose a `ScopeResolver` contract. Station can map this to a
tenant; a simple Laravel app can return `null`.

### Contact Integration

The package should not own contacts. It should define a `ContactResolver`
contract that receives a phone number and returns a lightweight identity:

- display name
- external model type/id, optional
- URL, optional
- metadata, optional

This keeps CRM, address book, and Station integrations outside the core package.

### Persistence

The default package should ship Eloquent models and migrations. Persistence
should not be swappable in v0.1 unless there is a concrete need. The stable
extension points should be contracts around lookup, routing, notifications, and
logging.

### Provider Layer

Twilio-specific behavior should live under a provider namespace, but the public
API should use package language where possible:

- `PhoneNumber`, not `TwilioNumber`
- `Message`, not `TwilioMessage`
- `Call`, not `TwilioCall`
- `WebhookReceipt`, not `TwilioWebhook`

Provider IDs and raw payloads should still be retained for debugging and
idempotency.

## Milestones

### M0 - Package Foundation

Goal: Make the repository contributor-ready.

Deliverables:

- Testbench-based package test setup.
- PHPUnit or Pest test suite.
- CI workflow for tests and Composer validation.
- Coding-style tooling.
- Publishable config file.
- Initial service provider wiring.
- Basic docs for installation and local testing.

Acceptance criteria:

- `composer install` works in a fresh checkout.
- The package boots inside a Testbench Laravel application.
- CI runs on pull requests.

### M1 - Twilio Configuration And Client

Goal: Centralize Twilio credentials and provider access.

Deliverables:

- `config/phone.php`.
- `PhoneManager` or equivalent facade/service.
- Twilio client factory using `twilio/sdk`.
- Support for account SID, auth token, default messaging service SID, default
  from number, and webhook route prefix.
- Provider fake for tests.

Acceptance criteria:

- Application code can resolve a configured phone service from the container.
- Missing credentials fail with actionable exceptions.
- Tests can run without real Twilio credentials.

### M2 - Webhook Security And Receipts

Goal: Accept Twilio webhooks safely and make delivery debuggable.

Deliverables:

- Twilio signature validation middleware.
- Configurable bypass for local tests only.
- `phone_webhook_receipts` migration/model.
- Idempotency by provider, event type, and provider SID where available.
- Normalized webhook payload objects.
- Webhook replay support for failed receipts, at least internally.

Acceptance criteria:

- Invalid signatures are rejected.
- Duplicate webhook deliveries do not duplicate business records.
- Raw payloads are stored with enough metadata to debug processing.

### M3 - Core Data Model

Goal: Create the package's durable business phone records.

Deliverables:

- `phone_numbers`
- `phone_threads`
- `phone_messages`
- `phone_calls`
- `phone_recordings`
- `phone_voicemails`

Baseline fields:

- scope columns
- provider name
- provider SID
- local number
- remote number
- direction
- status
- timestamps from provider
- raw provider metadata
- error code/message where relevant

Acceptance criteria:

- Models and migrations install cleanly.
- Provider SID fields are unique where Twilio guarantees uniqueness.
- Records can be queried by local number, remote number, scope, and status.

### M4 - SMS Inbound And Outbound

Goal: Support real SMS workflows before any UI exists.

Deliverables:

- Inbound SMS webhook endpoint.
- Thread creation and lookup.
- Message persistence for inbound SMS and MMS metadata.
- `InboundMessageReceived` event.
- Outbound SMS service.
- Queued outbound send job.
- Message status callback endpoint.
- `OutboundMessageSent`, `MessageDeliveryUpdated`, and failure events.
- Opt-out keyword detection and hooks.

Acceptance criteria:

- A Twilio inbound SMS creates or updates a thread and message.
- Application code can send an outbound SMS with one service call.
- Status callbacks update the same message record.
- Duplicate inbound or callback webhooks are safe.

### M5 - Voice Forwarding And Call Logging

Goal: Make a Twilio number usable as a business phone line.

Deliverables:

- Inbound voice webhook endpoint.
- Call status callback endpoint.
- Call persistence.
- Route decision object.
- `CallRouter` contract.
- Default routing modes:
  - forward to number
  - voicemail
  - reject
  - hang up
- TwiML response generation.
- Missed-call event.

Acceptance criteria:

- An inbound call can be forwarded to a configured phone number.
- The package records call status updates.
- A failed or unanswered call can fall back to voicemail.
- The route decision is visible in stored call metadata.

### M6 - Business Hours And Voicemail

Goal: Cover the minimum business phone behavior users expect.

Deliverables:

- Business-hours value objects/configuration.
- Default business-hours router.
- Voicemail TwiML response flow.
- Recording callback endpoint.
- Recording/voicemail persistence.
- Optional transcript field and transcription status.
- `VoicemailCreated` event.

Acceptance criteria:

- Calls outside business hours can go directly to voicemail.
- Completed recordings create voicemail records.
- Applications can notify a team member when voicemail arrives.

### M7 - Extension Contracts

Goal: Let real apps integrate without forking the package.

Deliverables:

- `ScopeResolver`
- `ContactResolver`
- `PhoneNumberResolver`
- `CallRouter`
- `MessagePolicy`
- `OptOutPolicy`
- `ActivityLogger`
- `TeamNotifier`
- `AiSessionHandler` placeholder contract

Acceptance criteria:

- Station can be shimmed using contracts, not package edits.
- A non-Station Laravel app can use defaults for every contract.
- Contracts are documented with examples.

### M8 - Operations, Compliance, And Safety

Goal: Make the package safe enough for production use.

Deliverables:

- Configurable payload redaction.
- Retention policy hooks for raw webhook payloads and message bodies.
- CLI health check command.
- CLI webhook replay command.
- Rate-limit guidance for outbound sends.
- 10DLC documentation and setup checklist.
- Error taxonomy for Twilio/provider failures.

Acceptance criteria:

- Operators can tell whether Twilio credentials/routes are configured.
- Failed webhook processing can be inspected and replayed.
- Docs explain the application's responsibilities for consent and 10DLC.

### M9 - Documentation And Examples

Goal: Make adoption possible without reading source.

Deliverables:

- Installation guide.
- Twilio console setup guide.
- Webhook URL reference.
- Local development guide using Twilio test credentials or ngrok.
- Example controller/job for appointment reminder SMS.
- Example call-forwarding configuration.
- Testing guide.

Acceptance criteria:

- A new Laravel app can receive an inbound SMS by following docs.
- A new Laravel app can forward an inbound call by following docs.
- No Station knowledge is required.

### M10 - Companion Packages

Goal: Build product surfaces without bloating the core package.

Deliverables:

- `fissible/phone-filament` scope document.
- `fissible/station-phone` scope document.
- Integration examples proving the core contracts are sufficient.

Acceptance criteria:

- The core package remains usable without Filament or Station.
- Station integration can map phone activity to CRM and AI modules externally.

## V0.1 Definition Of Done

V0.1 should be the first release someone can use for a real, simple Twilio
business line.

Required:

- Laravel package boots through auto-discovery.
- Config can be published.
- Migrations can be published or loaded.
- Twilio signatures are verified.
- Inbound SMS is stored in threads.
- Outbound SMS can be sent through a queued job.
- SMS status callbacks update persisted records.
- Inbound calls can be forwarded with TwiML.
- Missed/unanswered calls can fall back to voicemail.
- Voicemail recordings are stored as records.
- Core events are dispatched.
- Tests cover webhook validation, idempotency, SMS, call forwarding, and
  voicemail.
- README has a quickstart.

Not required for v0.1:

- Shared inbox UI.
- Browser softphone.
- Realtime AI answering.
- Station adapter.
- Full IVR builder.
- Twilio number purchasing.
- Automated 10DLC registration.

## Suggested Issue Breakdown

- Bootstrap package tests and CI.
- Add publishable config and service provider wiring.
- Implement Twilio client factory and test fake.
- Implement Twilio signature validation middleware.
- Add webhook receipt migration/model.
- Add phone number/thread/message migrations/models.
- Implement inbound SMS webhook.
- Implement outbound SMS service and queued job.
- Implement SMS status callback handling.
- Add call/recording/voicemail migrations/models.
- Implement inbound voice webhook and forwarding TwiML.
- Implement call status callback handling.
- Implement voicemail recording callback handling.
- Add business-hours routing.
- Define integration contracts.
- Add docs for Twilio setup and local development.

## Deferred Backlog

- Filament inbox and settings UI.
- Station adapter.
- Post-call AI summaries.
- Realtime AI answering with Conversation Relay or Agent Connect.
- Browser calling with Twilio Voice JS SDK.
- IVR/menu builder.
- Multi-provider support beyond Twilio.
- Import/sync of existing Twilio numbers.
- Twilio Conversations API adapter.
- Media download/storage pipeline for MMS and recordings.
