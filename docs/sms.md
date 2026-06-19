# SMS

## Inbound

When Twilio posts to `POST /phone/twilio/sms/inbound`, the package:

1. validates the signature and records a webhook receipt;
2. resolves (or creates) the local `phone_numbers` row from the `To` number;
3. resolves (or creates) the `phone_threads` row for the local/remote pair;
4. enriches thread identity via the `ContactResolver` contract;
5. stores the `phone_messages` row as `received`;
6. applies the opt-out policy (STOP/START);
7. dispatches `Fissible\Phone\Events\InboundMessageReceived` after persistence.

Inbound scope is copied from the matched local number — never from request
context, since webhooks have no authenticated session.

Pre-create `phone_numbers` rows when you need tenant-specific scoping; otherwise
unknown inbound locals are created in the configured default scope
(`PHONE_DEFAULT_SCOPE_KEY`).

## Outbound

```php
use Fissible\Phone\Facades\Phone;

Phone::messages()
    ->to('+16615551212')
    ->body("We're on for this morning.")
    ->send();      // persists, sends synchronously, returns the PhoneMessage
```

Queue it instead with `->queue()` to dispatch the guarded send job through the
bus. Either way the send is **idempotent at the row level**: the job atomically
claims a `queued` row and exits if it already has a provider SID or is no longer
sendable. An ambiguous provider timeout is marked `send_unknown` rather than
blindly retried, so a customer is never double-texted.

### Sender precedence

1. explicit Messaging Service SID
2. configured default Messaging Service SID
3. explicit `from` number
4. configured default `from` number

### Unknown recipients

By default outbound is blocked unless the recipient already has a thread for the
chosen local number. Opt in per send with `->allowUnknownRecipient()` or globally
with `PHONE_SMS_ALLOW_UNKNOWN_RECIPIENTS=true`. Threads with `opted_out_at` set
are always blocked by the default `MessagePolicy`.

### Contact attribution

```php
Phone::messages()
    ->to('+16615551212')
    ->body('Crew is on site.')
    ->contact(type: 'lead', id: 123, name: 'Sam Lead')
    ->send();
```

Stored on `phone_messages.metadata.contact`, and mirrored onto the thread's
`remote_display_name`, `contact_type`, and `contact_id` when a thread exists.

## Delivery status

Twilio posts to `POST /phone/twilio/sms/status`. The package looks up the
outbound row by provider SID and applies a deterministic status progression:
lower-rank callbacks are ignored, terminal states never regress, and carrier
failure details are stored on the message. `MessageDeliveryUpdated` dispatches
only after the update persists.

## Opt-out

Inbound STOP-style keywords set `phone_threads.opted_out_at`; START-style clear
it (`ThreadOptedOut` / `ThreadOptedIn` events). Replace the keyword lists in
config, or bind your own `OptOutPolicy`.

## Events

`InboundMessageReceived`, `OutboundMessageQueued`, `OutboundMessageSent`,
`OutboundMessageFailed`, `MessageDeliveryUpdated`, `ThreadOptedOut`,
`ThreadOptedIn`.
