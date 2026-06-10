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
