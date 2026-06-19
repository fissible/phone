# Compliance

This package provides hooks and defaults, not legal guarantees. You are
responsible for meeting messaging and recording regulations in your jurisdiction.

## 10DLC / A2P registration

US application-to-person SMS requires 10DLC brand and campaign registration. Use
a Twilio **Messaging Service** as your sender — it manages the campaign, sender
pool, sticky-sender behavior, and delivery callbacks:

```env
TWILIO_MESSAGING_SERVICE_SID=MGxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Registration itself is your responsibility and is not automated by this package.

## Consent

Obtain consent before sending outbound SMS. The default `MessagePolicy` blocks
sends to threads with `opted_out_at` set; do not bypass it for marketing traffic.

## STOP / START

Opt-out keyword handling is on by default (`sms.opt_out`). Inbound STOP-style
keywords set `phone_threads.opted_out_at`; START-style clear it. Keep your own
suppression list in sync via the `ThreadOptedOut` / `ThreadOptedIn` events if you
send from other systems.

## Recording consent

Recording calls (including voicemail) may require notifying or obtaining consent
from callers depending on jurisdiction. Add a spoken disclosure to your greeting
where required.

## Message and payload retention

- Disable raw payload storage if you do not want bodies/numbers persisted on
  receipts: `webhooks.store_raw_payloads => false`, and redact keys via
  `webhooks.redact`.
- Run [`phone:prune`](commands.md) on a schedule to enforce `retention`.

## Media lifecycle

Twilio MMS and recording media URLs may require authentication and are not
retained indefinitely. If you need long-term media, fetch and store it in your
own storage before Twilio's retention window expires. The package stores media
URLs but does not download media.
