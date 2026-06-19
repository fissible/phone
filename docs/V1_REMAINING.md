# V1 Remaining Work

Stateless plan for the work between the current build and `v1.0.0`. Source of
truth for the remaining 1.0 scope. See `V1_DESIGN.md` for full design and
`RELEASE.md` for the release ladder.

## Decisions (2026-06-19)

- **AI handoff:** build the **core boundary** for 1.0 — `ai` route decision,
  Conversation Relay TwiML, `phone_ai_sessions`, `AiSessionHandler` contract,
  AI events, working `/ai/status`. **No** bundled LLM/WebSocket runtime. The
  BYO-key realtime runtime (Prism + Node/Swoole WS sidecar) is a future
  `fissible/phone-ai` companion package. Core stays LLM-free.
- **Production gate:** relaxed to an **RC smoke test** (tunnel + one live Twilio
  number, documented manual run of all 5 flows) → tag `v1.0.0-rc` → promote to
  `1.0.0` after real usage accrues.

## Status of the core

Built + tested (64 tests, strict validate, pint, CI 8.2–8.4 × Laravel 11/12):
webhooks, SMS in/out, voice forward, business hours, voicemail, transcription,
8 contracts with defaults, `phone:doctor`. Eligible to tag `v0.1.0-alpha` now.

## Remaining work — dependency-ordered (leaves → roots)

### Phase A — AI boundary (core, no runtime). Blocks ai docs + API freeze.

- `A1` `phone_ai_sessions` migration + `PhoneAiSession` model. *(leaf)* — **DONE**
- `A2` Add `ai` mode + constant to `RouteDecision` value object. *(leaf)*
- `A3` Conversation Relay attribute value object (ws url, voice, language,
  greeting, barge-in, auth/session params, metadata). *(leaf)*
- `A4` `AiSessionHandler` contract + disabled default + service-provider binding.
  Depends: A3.
- `A5` Conversation Relay TwiML builder on `TwilioVoiceTwiMLBuilder`.
  Depends: A2, A3.
- `A6` Wire `ai` decision into `InboundVoiceProcessor`: emit CR TwiML + persist
  `phone_ai_sessions`. Depends: A1, A4, A5.
- `A7` Implement `aiStatus` controller: update session, dispatch events.
  Depends: A1.
- `A8` AI events: `AiSessionStarted`, `AiSessionEnded`, `AiSessionFailed`.
  Depends: A1.

### Phase B — Operations commands. Independent of A.

- `B1` `phone:prune` + `PruneWebhookReceipts` job enforcing `retention` config.
  **Highest value** — nothing currently enforces retention; raw payloads
  accumulate forever (storage + privacy). *(leaf)*
- `B2` `phone:webhook:replay {receipt}` — CLI over existing `WebhookReplayService`.
  *(leaf)*
- `B3` `phone:install` — publish config + migrations + print next steps. *(leaf)*

### Phase C — Docs pass. Depends on A + B settled so docs match shipped surface.

- `C1` Restructure `docs/` into task-oriented guides: index, configuration,
  twilio-setup, sms, voice, voicemail, webhooks-security, testing, compliance,
  production-checklist, ai-handoff. Keep SCOPE/ROADMAP/V1_DESIGN/RELEASE as
  internal planning docs.

### Phase D — API freeze. Depends on A.

- `D1` Reconcile event names (design: `CallForwarded`/`CallMissed`/
  `CallCompleted`/`RecordingAvailable`/`VoicemailCreated` vs shipped
  `CallStatusUpdated`/`RecordingStatusUpdated`/`VoicemailReceived`). Pick final
  names, document the stable surface (facade, contracts, events, migrations).

### Phase E — Release ladder.

- `E1` Tag `v0.1.0-alpha` now (core usable). Submit to Packagist.
- `E2` After A–D: `v1.0.0-rc`.
- `E3` RC smoke test (tunnel + live number, 5 flows documented) → `v1.0.0`.

## Suggested order

E1 (now) → A1, A2, A3 → A4, A5 → A6, A7, A8 → B1, B2, B3 → C1 → D1 → E2 → E3.

## Session handoff notes

- 2026-06-19: Plan created from two scoping decisions above. `A1` done via TDD
  (`phone_ai_sessions` migration + `PhoneAiSession` model,
  `tests/Feature/Ai/PhoneAiSessionTest.php`; suite 66 passing, pint clean).
  Not yet committed (on `main`). Next: `A2` (`ai` mode on `RouteDecision`) and
  `A3` (Conversation Relay attribute value object) — both leaves.
