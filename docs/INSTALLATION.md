# Installation

This package is pre-`1.0`. Use it in real applications with a pinned version or
commit until the public contracts are tagged stable.

## Install

```bash
composer require fissible/phone
php artisan vendor:publish --tag=phone-config
php artisan vendor:publish --tag=phone-migrations
php artisan migrate
```

The service provider is auto-discovered by Laravel.

## Environment

```env
PHONE_PROVIDER=twilio
PHONE_ROUTE_PREFIX=phone

TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_FROM=+16615550100
TWILIO_MESSAGING_SERVICE_SID=MG...
TWILIO_VALIDATE_WEBHOOKS=true

PHONE_WEBHOOK_BASE_URL=https://example.com

PHONE_DEFAULT_SCOPE_KEY=global
PHONE_FORWARD_TO=+16615559999
PHONE_TIMEZONE=America/Los_Angeles
PHONE_TRANSCRIBE_VOICEMAILS=false
PHONE_SMS_ALLOW_UNKNOWN_RECIPIENTS=false
```

Use `PHONE_WEBHOOK_BASE_URL` in production, especially behind Forge, load
balancers, or TLS-terminating proxies. Twilio signs the public URL, not the
internal URL Laravel may see from the upstream proxy.

## Twilio URLs

With the default `PHONE_ROUTE_PREFIX=phone`, configure Twilio with these public
URLs:

| Twilio setting | URL |
| --- | --- |
| Incoming SMS webhook | `https://example.com/phone/twilio/sms/inbound` |
| SMS status callback | `https://example.com/phone/twilio/sms/status` |
| Incoming voice webhook | `https://example.com/phone/twilio/voice/inbound` |
| Voice status callback | `https://example.com/phone/twilio/voice/status` |

The package generates per-flow callback URLs for dial action, recording, and
transcription callbacks when needed.

For outbound SMS, prefer a Twilio Messaging Service SID. Twilio describes
Messaging Services as a higher-level grouping for senders, shared settings, and
features such as sender pools and compliance configuration:
https://www.twilio.com/docs/messaging/services

## A2P 10DLC

For application-sent SMS/MMS to US recipients from a 10-digit long code, register
for A2P 10DLC before launch:
https://www.twilio.com/docs/messaging/compliance/a2p-10dlc

The package does not register your Brand or Campaign. Do that in Twilio Console
or through Twilio APIs, then use the approved sender or Messaging Service in
this package.

Before sending production reminders:

- Register the Brand and Campaign for the business/use case.
- Attach the number to the approved Messaging Service or campaign path.
- Keep opt-in language and proof outside this package.
- Keep `PHONE_SMS_ALLOW_UNKNOWN_RECIPIENTS=false` unless a send deliberately
  opts in with `allowUnknownRecipient()`.
- Confirm current carrier rules, fees, and review times in Twilio before launch.

The package handles local STOP/START tracking and blocks outbound sends to
threads with `opted_out_at` set. Carrier compliance still depends on Twilio
registration and your consent practices.

## Outbound SMS

```php
use Fissible\Phone\Facades\Phone;

Phone::messages()
    ->to($lead->phone)
    ->body("We're on for this morning.")
    ->contact(
        type: 'lead',
        id: $lead->id,
        name: $lead->name,
        url: route('leads.show', $lead),
        metadata: ['campaign' => 'jobsite-cameras'],
    )
    ->queue();
```

Outbound contact attribution is stored on `phone_messages.metadata.contact`.
When the send is associated with a thread, the same contact is stored on
`phone_threads.metadata.contact` and mirrored to `remote_display_name`,
`contact_type`, and `contact_id`.

The send job is idempotent at the `phone_messages` row level. If a queue retry
runs after Twilio has accepted the message, the job sees the provider SID or
non-queued status and exits without sending a duplicate.

## Inbound SMS

Inbound SMS creates or updates:

- `phone_numbers`
- `phone_threads`
- `phone_messages`
- `phone_webhook_receipts`

Bind `Fissible\Phone\Contracts\ContactResolver` to match inbound texts to host
records:

```php
use Fissible\Phone\Contracts\ContactResolver;
use Fissible\Phone\ValueObjects\ContactIdentity;
use Fissible\Phone\ValueObjects\ContactLookup;

class AppContactResolver implements ContactResolver
{
    public function resolve(ContactLookup $lookup): ContactIdentity
    {
        $lead = Lead::query()->where('phone', $lookup->remoteNumber)->first();

        if (! $lead) {
            return ContactIdentity::anonymous($lookup->remoteNumber);
        }

        return new ContactIdentity(
            displayName: $lead->name,
            externalType: 'lead',
            externalId: (string) $lead->id,
            url: route('leads.show', $lead),
        );
    }
}
```

Register the binding in an application service provider:

```php
$this->app->bind(ContactResolver::class, AppContactResolver::class);
```

## Voice Forwarding And Voicemail

Set `PHONE_FORWARD_TO` for the simple one-number path. Inbound calls are recorded
in `phone_calls` and forwarded during open hours. If no forward destination is
configured, or the call is outside configured business hours and
`after_hours_mode` is `voicemail`, Twilio receives voicemail TwiML.

Business hours live in `config/phone.php`:

```php
'business_hours' => [
    'timezone' => env('PHONE_TIMEZONE', 'America/Los_Angeles'),
    'weekly' => [
        'monday' => [['start' => '09:00', 'end' => '17:00']],
        'tuesday' => [['start' => '09:00', 'end' => '17:00']],
        'wednesday' => [['start' => '09:00', 'end' => '17:00']],
        'thursday' => [['start' => '09:00', 'end' => '17:00']],
        'friday' => [['start' => '09:00', 'end' => '17:00']],
    ],
    'holidays' => [],
],
```

Inbound call contact resolution is deferred with
`Fissible\Phone\Jobs\ResolveInboundCallContact`, dispatched after the voice
webhook response. That keeps the TwiML path fast. Resolved call contacts are
stored in `phone_calls.metadata.contact`; resolver failures are captured in
`phone_calls.metadata.contact_resolution` and do not fail the webhook.

## Team Notifications

Bind `Fissible\Phone\Contracts\TeamNotifier` to route operational notifications
to email, Slack, push, or the host app's notification system:

```php
use Fissible\Phone\Contracts\TeamNotifier;
use Fissible\Phone\ValueObjects\TeamNotification;

class AppTeamNotifier implements TeamNotifier
{
    public function notify(TeamNotification $notification): void
    {
        Notification::route('mail', config('services.leads.notify_to'))
            ->notify(new PhoneEventNotification($notification));
    }
}
```

The package emits notifications for inbound SMS, missed inbound calls, and new
voicemails.

## Diagnostics

```bash
php artisan phone:doctor
php artisan phone:doctor --live
```

The live check makes one Twilio API call to confirm credentials.

## Testing

Use the provider fake in application tests:

```php
$fake = Phone::fake();

Phone::messages()
    ->to('+16615551212')
    ->body('Crew is on site.')
    ->allowUnknownRecipient()
    ->send();

expect($fake->messages())->toHaveCount(1);
```
