# Fissible Phone Documentation

Laravel-native Twilio SMS and voice: durable threads, call routing, voicemail,
webhook security, and AI-answering handoff — all UI-free and extendable through
contracts.

## Getting started

- [Installation](INSTALLATION.md) — install, publish, migrate
- [Twilio console setup](twilio-setup.md) — number, webhooks, Messaging Service
- [Configuration](configuration.md) — every config key and environment variable

## Guides

- [SMS](sms.md) — inbound threads, outbound send/queue, delivery status, opt-out
- [Voice](voice.md) — call forwarding, business hours, dial fallback, call status
- [Voicemail](voicemail.md) — recording and transcription
- [AI handoff](ai-handoff.md) — Conversation Relay routing and session lifecycle
- [Webhook security](webhooks-security.md) — signatures, proxy base URL, CSRF, replay
- [Console commands](commands.md) — `phone:install`, `phone:doctor`, `phone:prune`, `phone:webhook:replay`
- [Testing](testing.md) — the provider fake

## Operating in production

- [Compliance](compliance.md) — 10DLC, consent, STOP/START, media retention
- [Production checklist](production-checklist.md)

## Reference

- [Stable API (1.0)](stable-api.md) — the semver-covered public surface

## Extension contracts

Every integration point ships a safe default and can be rebound by the host app.

| Contract | Default | Purpose |
| --- | --- | --- |
| `ScopeResolver` | `global` scope | Tenant/scope for app-initiated operations |
| `PhoneNumberResolver` | E.164 lookup | Map inbound numbers to `PhoneNumber` models |
| `ContactResolver` | anonymous | Map a remote number to a display identity |
| `CallRouter` | business-hours router | Decide inbound call routing |
| `MessagePolicy` | block opted-out | Whether an outbound message may send |
| `OptOutPolicy` | US SMS keywords | STOP/START handling |
| `ActivityLogger` | no-op | Mirror events into a CRM/audit log |
| `TeamNotifier` | no-op | Notify people of inbound SMS, missed calls, voicemail |
| `AiSessionHandler` | disabled | Supply AI answering settings; receive lifecycle events |

## Planning / internal

[Scope](SCOPE.md) · [Roadmap](ROADMAP.md) · [V1 Design](V1_DESIGN.md) ·
[V1 Remaining](V1_REMAINING.md) · [Release policy](RELEASE.md) ·
[Changelog](../CHANGELOG.md)
