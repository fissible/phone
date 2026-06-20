# Changelog

All notable changes to `fissible/phone` will be documented in this file.

This project follows semantic versioning once it reaches `1.0.0`. Before
`1.0.0`, minor versions may include breaking changes while the package boundary
is still being proven by real applications.

## Unreleased

## v1.0.0-rc.2 - 2026-06-19

### Added

- Outbound call origination: `Phone::calls()->to()->callerId()->twiml()/url()
  ->send()/queue()`, backed by an idempotent `SendOutboundCall` job (with
  `send_unknown` handling), a `createCall` method on the `PhoneProvider`
  contract (Twilio + fake), and `OutboundCallQueued` / `OutboundCallInitiated` /
  `OutboundCallFailed` events. Outbound calls persist as `direction=outbound`
  `phone_calls` rows and are updated by the existing voice status callback.

## v1.0.0-rc.1 - 2026-06-19

Release candidate for the first stable line. Public surface frozen per
`docs/stable-api.md`; promote to `1.0.0` after the RC smoke test against a live
Twilio number.

### Added

- AI handoff boundary (Twilio Conversation Relay): `ai` route decision and
  `ConversationRelayConfig`, `Connect`/`ConversationRelay` TwiML, the
  `AiSessionHandler` contract (disabled by default), `phone_ai_sessions`
  persistence, the working `/phone/twilio/ai/status` callback, and
  `AiSessionStarted` / `AiSessionEnded` / `AiSessionFailed` events. Core stays
  LLM-free; the realtime runtime is bring-your-own.
- Operations commands: `phone:install`, `phone:prune` (enforces receipt
  retention via the `PruneWebhookReceipts` job), and `phone:webhook:replay`
  (reprocesses a stored receipt through its matching processor).
- Task-oriented documentation guide set under `docs/`.
- Outbound contact attribution for messages and SMS threads.
- Deferred inbound voice contact resolution.
- Team notification hooks for inbound SMS, missed calls, and voicemail.
- Contact and activity extension contracts.
- Business-hours voice routing.
- Voicemail recording and transcription callbacks.
- Webhook receipts, replay support, and proxy-safe Twilio validation.
