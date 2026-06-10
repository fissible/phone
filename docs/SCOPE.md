# Scope

## Product Direction

Fissible Phone should be the Laravel foundation for a programmable business
phone system. It should make Twilio feel like a Laravel-native subsystem without
turning into a single-purpose app.

The package should support small-business workflows first:

- one or more Twilio numbers
- inbound and outbound SMS
- call forwarding
- missed-call capture
- voicemail
- business hours
- message/call status callbacks
- team notification hooks
- contact matching hooks
- AI answering hooks

The package should not assume the consuming application has a CRM, an admin UI,
or an AI module. Those should be integrations.

## Repository Boundary

This repository owns the generic Laravel package:

- Twilio API client configuration
- Twilio webhook request validation
- normalized webhook payload objects
- inbound message/call/recording persistence
- outbound message/call services
- queue jobs and retries
- TwiML response generation for common flows
- Laravel events for application workflows
- contracts for contact lookup, routing, policy, and AI handoff

This repository should not own:

- Station module registration
- Station tenant scoping
- Station CRM activity models
- Station AI prompt/agent configuration
- a mandatory Filament UI
- business-specific text templates

## Likely Companion Packages

### `fissible/phone-filament`

Optional admin UI for Laravel applications using Filament.

Responsibilities:

- shared SMS inbox
- thread detail view
- call log
- voicemail list/player
- phone number settings
- routing rules
- templates

### `fissible/station-phone`

Station-specific shim.

Responsibilities:

- Station module registration
- tenant-aware configuration
- CRM contact matching
- CRM timeline/activity logging
- Station permissions and menu contributions
- Fissible AI bridge for answering, summaries, and classification

## Core Concepts

### Phone Number

A local representation of a Twilio number, including routing mode, messaging
capabilities, forwarding targets, and ownership metadata.

### Thread

A conversation grouping for SMS/MMS between a local number and an external
participant. The default grouping should be `(tenant/application scope, local
number, remote number)`, but the package should expose enough hooks for apps to
override ownership or contact matching.

### Message

Inbound or outbound SMS/MMS, with Twilio SID, direction, delivery status, body,
media metadata, error details, and timestamps.

### Call

Inbound or outbound voice call, with Twilio call SID, direction, status,
duration, caller/callee numbers, routing outcome, and timestamps.

### Voicemail

A call recording plus metadata, transcription status, optional transcript, and
application notification state.

### Webhook Receipt

Durable record of a Twilio webhook delivery. This gives the package idempotency,
debuggability, and replay/testing support.

### AI Session

Optional abstraction for AI answering or post-call processing. The generic
package should define the boundaries and events, while concrete provider logic
belongs in an adapter.

## First Version

The first useful release should do four things well:

1. Verify and accept Twilio webhooks safely.
2. Store inbound SMS in durable threads.
3. Send outbound SMS from Laravel code and track delivery status.
4. Return TwiML for a reliable call-forwarding and voicemail fallback flow.

That is enough to support a real business number before building the richer UI
and AI layers.

## Open Questions

- Should multi-tenancy be modeled directly, or only exposed through scope
  resolver contracts?
- Should message storage be mandatory, or should persistence be swappable?
- Should the package include a minimal Blade/Livewire inbox, or leave all UI to
  companion packages?
- Should Conversation Relay require a separate long-running WebSocket service,
  or can the first AI milestone be post-call summarization and triage?
