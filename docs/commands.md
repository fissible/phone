# Console Commands

## `phone:install`

```bash
php artisan phone:install [--force]
```

Publishes `config/phone.php` and the migrations, then prints setup next steps.
`--force` overwrites existing published files. Run `php artisan migrate`
afterwards.

## `phone:doctor`

```bash
php artisan phone:doctor [--live]
```

Offline configuration check: provider, Twilio credentials, sender, webhook base
URL, stateless webhook middleware, and default voice routing. `--live` makes a
single Twilio API request to verify the credentials actually work. The default
(offline) run never contacts Twilio.

## `phone:webhook:replay`

```bash
php artisan phone:webhook:replay {receipt}
```

Reprocesses a stored `phone_webhook_receipts` row by rebuilding the original
request and running it through the matching processor (SMS inbound/status, voice
inbound/dial/status, recording, transcription, AI status). Records the outcome
and increments `replay_count`. Use it to recover failed receipts after a fix.

## `phone:prune`

```bash
php artisan phone:prune
```

Enforces the `retention` config: deletes webhook receipts older than
`retention.webhook_receipts_days` and strips raw payloads (and headers) from
receipts older than `retention.raw_payload_days`. Set either to `0` to disable
that step.

Schedule it daily:

```php
// routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::command('phone:prune')->daily();
```

The underlying `Fissible\Phone\Jobs\PruneWebhookReceipts` job is queueable if you
prefer to dispatch it directly.
