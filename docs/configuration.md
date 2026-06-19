# Configuration

Publish the config file with `php artisan vendor:publish --tag=phone-config`
(or `php artisan phone:install`). The package works without publishing; published
config is for customization.

All keys live in `config/phone.php`.

## Provider and routing

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `provider` | `PHONE_PROVIDER` | `twilio` | Active provider. Only `twilio` is supported. |
| `route_prefix` | `PHONE_ROUTE_PREFIX` | `phone` | URL prefix for the webhook routes. |

## Twilio credentials

| Key | Env | Purpose |
| --- | --- | --- |
| `twilio.account_sid` | `TWILIO_ACCOUNT_SID` | Twilio account SID. |
| `twilio.auth_token` | `TWILIO_AUTH_TOKEN` | Twilio auth token (used for signature validation). |
| `twilio.default_from` | `TWILIO_FROM` | Default sender number. |
| `twilio.messaging_service_sid` | `TWILIO_MESSAGING_SERVICE_SID` | Preferred sender (A2P/10DLC). |
| `twilio.validate_webhooks` | `TWILIO_VALIDATE_WEBHOOKS` | Validate signatures (default `true`). Disable only in local/test. |

## SMS

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `sms.allow_unknown_recipients` | `PHONE_SMS_ALLOW_UNKNOWN_RECIPIENTS` | `false` | Allow outbound to numbers with no existing thread. |
| `sms.opt_out.enabled` | `PHONE_SMS_OPT_OUT_ENABLED` | `true` | Honor STOP/START keywords. |
| `sms.opt_out.stop_keywords` | — | `STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT` | Opt-out keywords. |
| `sms.opt_out.start_keywords` | — | `START, YES, UNSTOP` | Opt-in keywords. |

## Default voice routing

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `default_voice.mode` | — | `forward` | Default routing mode. |
| `default_voice.forward_to` | `PHONE_FORWARD_TO` | — | Fallback forward destination. |
| `default_voice.timeout` | — | `20` | Dial timeout (seconds). |
| `default_voice.after_hours_mode` | — | `voicemail` | Mode used outside business hours. |
| `default_voice.voicemail_greeting` | `PHONE_VOICEMAIL_GREETING` | "Please leave a message after the tone." | Spoken before recording. |
| `default_voice.transcribe_voicemails` | `PHONE_TRANSCRIBE_VOICEMAILS` | `false` | Request Twilio voicemail transcription. |

## Business hours

`business_hours.timezone` (`PHONE_TIMEZONE`, defaults to `app.timezone`),
`business_hours.weekly`, and `business_hours.holidays`. See [Voice](voice.md) for
the weekly/holiday format. Empty `weekly` means always open.

## Numbers

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `numbers.create_unknown_inbound` | — | `true` | Auto-create `phone_numbers` for unknown inbound locals. |
| `numbers.default_scope_key` | `PHONE_DEFAULT_SCOPE_KEY` | `global` | Scope for auto-created numbers. |
| `numbers.default_scope_type` | `PHONE_DEFAULT_SCOPE_TYPE` | — | Optional scope metadata. |
| `numbers.default_scope_id` | `PHONE_DEFAULT_SCOPE_ID` | — | Optional scope metadata. |

## Webhooks

| Key | Env | Default | Purpose |
| --- | --- | --- | --- |
| `webhooks.base_url` | `PHONE_WEBHOOK_BASE_URL` | — | Public https URL for proxy-safe signature validation. |
| `webhooks.middleware` | — | `['phone.twilio']` | Route middleware (stateless, CSRF-free). |
| `webhooks.store_raw_payloads` | — | `true` | Persist raw payloads on receipts. |
| `webhooks.store_invalid_payloads` | — | `false` | Persist payloads for rejected (invalid-signature) requests. |
| `webhooks.redact` | — | `[]` | Payload keys to redact before storage. |
| `webhooks.replay_enabled` | — | `true` | Allow receipt replay. |

See [Webhook security](webhooks-security.md) for details.

## Retention

| Key | Default | Purpose |
| --- | --- | --- |
| `retention.webhook_receipts_days` | `90` | Delete receipts older than this. |
| `retention.raw_payload_days` | `30` | Strip raw payloads from receipts older than this. |

Enforced by [`phone:prune`](commands.md). Schedule it daily.
