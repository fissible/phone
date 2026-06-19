# Production Checklist

Before pointing a real Twilio number at your app:

## Configuration

- [ ] `TWILIO_ACCOUNT_SID` and `TWILIO_AUTH_TOKEN` set.
- [ ] A sender configured: `TWILIO_MESSAGING_SERVICE_SID` (preferred) or `TWILIO_FROM`.
- [ ] `PHONE_WEBHOOK_BASE_URL` set to your public `https://` URL.
- [ ] `TWILIO_VALIDATE_WEBHOOKS=true` (never disable in production).
- [ ] `PHONE_FORWARD_TO` (and/or per-number `forward_to`) set if forwarding calls.
- [ ] Business hours configured if you want after-hours routing.
- [ ] `php artisan phone:doctor --live` passes.

## Twilio console

- [ ] Number's **message** webhook → `…/phone/twilio/sms/inbound` (POST).
- [ ] Number's **voice** webhook → `…/phone/twilio/voice/inbound` (POST).
- [ ] 10DLC brand/campaign registered for US A2P SMS.

## Runtime

- [ ] Migrations run (`php artisan migrate`).
- [ ] A queue worker is running (outbound sends + contact resolution are queued).
- [ ] `phone:prune` scheduled daily (enforces retention; protects raw payloads).
- [ ] Laravel trusted proxies configured if behind a load balancer.

## Webhook routes

- [ ] The `/phone/twilio/*` routes are **not** in the `web`/session/CSRF group.
- [ ] A test inbound SMS creates a thread + message.
- [ ] A test call forwards (or goes to voicemail) and records call status.
- [ ] An unanswered call falls back to voicemail and stores the recording.

## AI handoff (only if enabled)

- [ ] A custom `AiSessionHandler` is bound (it is disabled by default).
- [ ] Your Conversation Relay WebSocket runtime is deployed and reachable.
- [ ] Conversation Relay status callback points at `…/phone/twilio/ai/status`.

## Compliance

- [ ] Consent obtained for outbound SMS; STOP/START honored.
- [ ] Recording disclosure added to greetings where required.
- [ ] Media retention plan in place if you need long-term media.

See [Compliance](compliance.md) for detail.
