# Webhook Security

## Routes

All routes are registered under `PHONE_ROUTE_PREFIX` (default `/phone`) with the
stateless `phone.twilio` middleware — **no session, no CSRF**.

| Method | Path | Name |
| --- | --- | --- |
| POST | `/phone/twilio/sms/inbound` | `phone.twilio.sms.inbound` |
| POST | `/phone/twilio/sms/status` | `phone.twilio.sms.status` |
| POST | `/phone/twilio/voice/inbound` | `phone.twilio.voice.inbound` |
| POST | `/phone/twilio/voice/dial-status` | `phone.twilio.voice.dial-status` |
| POST | `/phone/twilio/voice/status` | `phone.twilio.voice.status` |
| POST | `/phone/twilio/voice/recording` | `phone.twilio.voice.recording` |
| POST | `/phone/twilio/voice/transcription` | `phone.twilio.voice.transcription` |
| POST | `/phone/twilio/ai/status` | `phone.twilio.ai.status` |

## Signature validation

Every request is validated against the Twilio signature using your auth token,
unless `TWILIO_VALIDATE_WEBHOOKS=false` (local/test only). Invalid signatures are
rejected with `403` after a minimal forensic receipt is written.

### Behind a proxy

If your app sits behind a TLS-terminating proxy, Laravel may see an internal
`http://` host while Twilio signed the public `https://` URL — breaking
validation. Set the public base URL and the validator reconstructs the exact
signed URL (base + path + query):

```env
PHONE_WEBHOOK_BASE_URL=https://your-app.example.com
```

Generated callback URLs (SMS status, dial actions, recordings, AI status) also
use this base URL when set. Configure Laravel trusted proxies too, but signature
validation does not depend on proxy headers being perfect.

### CSRF

Twilio POSTs carry no CSRF token. The package routes are intentionally stateless
so they cannot return `419`. **Do not** add `web`, session, or CSRF middleware to
these routes — it is unsupported.

## Receipts and idempotency

Every accepted webhook is stored in `phone_webhook_receipts` with the
reconstructed URL, signature result, request hash, provider SID, processing
status, redacted headers, and (optionally) the payload. Exact retries are
deduplicated by request hash, so duplicate deliveries never duplicate business
records.

Control payload storage:

```php
'webhooks' => [
    'store_raw_payloads' => true,    // store payloads on accepted receipts
    'store_invalid_payloads' => false, // store payloads on rejected receipts
    'redact' => ['Body'],            // keys redacted before storage
],
```

## Replay

Failed receipts can be reprocessed after you fix a bug or a transient failure:

```bash
php artisan phone:webhook:replay 12345
```

This rebuilds the original request from the stored receipt and runs it through
the matching processor, then records the outcome and increments `replay_count`.
See [Commands](commands.md).
