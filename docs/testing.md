# Testing

The package ships a provider fake so your application tests never touch Twilio.

```php
use Fissible\Phone\Facades\Phone;

$fake = Phone::fake();

Phone::messages()
    ->to('+16615551212')
    ->body('Crew is on site.')
    ->allowUnknownRecipient()
    ->send();

// Inspect what would have been sent to Twilio:
$fake->messages();
```

The fake captures outbound API calls and returns realistic provider SIDs, so the
normal persistence, status-progression, and idempotency paths all run unchanged.

## Webhook tests

Disable signature validation in the test environment and POST the Twilio
parameters directly to the routes:

```php
config()->set('phone.twilio.validate_webhooks', false);

$this->post('/phone/twilio/sms/inbound', [
    'MessageSid' => 'SM'.str_repeat('1', 32),
    'AccountSid' => 'AC'.str_repeat('9', 32),
    'From' => '+16615551212',
    'To' => '+16615550100',
    'Body' => 'Hello',
    'NumMedia' => '0',
])->assertNoContent();
```

Assert on the resulting `phone_threads` / `phone_messages` rows and on dispatched
events with `Event::fake([...])`.

## No real credentials

No test in this package requires real Twilio credentials, and yours should not
either. Use the fake for outbound and direct webhook POSTs for inbound.
