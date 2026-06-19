# AI Handoff

The package can hand an inbound call to an AI agent using
[Twilio Conversation Relay](https://www.twilio.com/docs/voice/conversationrelay).
It owns the **boundary** — routing, TwiML, persistence, and events — and is
deliberately **LLM-free**. The realtime agent runtime is yours (bring your own
keys).

## What is in core vs. yours

```
Caller → Twilio number → /voice/inbound returns:
   <Connect><ConversationRelay url="wss://your-app/..." .../>
Twilio opens a WebSocket to YOUR server (Twilio does speech↔text).
Your server gets transcribed text → calls an LLM → streams reply text back.
```

| In this package | Yours (companion / app) |
| --- | --- |
| `ai` route decision + `ConversationRelayConfig` | the `wss://` WebSocket server |
| `<Connect><ConversationRelay>` TwiML | the LLM call + API key |
| `phone_ai_sessions` persistence | prompt/tool logic |
| `AiSessionStarted/Ended/Failed` events | barge-in / transcript UX |
| `/ai/status` callback handling | — |

Cost: this package burns **no tokens**. Twilio bills Conversation Relay (ASR/TTS)
and minutes; your runtime holds the LLM key and is billed for tokens.

## Enabling it

AI is **disabled by default**. Bind your own `AiSessionHandler`:

```php
use Fissible\Phone\Contracts\AiSessionHandler;
use Fissible\Phone\ValueObjects\CallContext;
use Fissible\Phone\ValueObjects\ConversationRelayConfig;

class ReceptionistHandler implements AiSessionHandler
{
    public function shouldHandle(CallContext $call): bool
    {
        // e.g. only after hours, or only for a flagged number
        return $call->phoneNumber->routing_mode === 'ai';
    }

    public function configure(CallContext $call): ConversationRelayConfig
    {
        return new ConversationRelayConfig(
            websocketUrl: config('services.my_ai.relay_url'),
            voice: 'en-US-Neural',
            language: 'en-US',
            welcomeGreeting: 'Thanks for calling Acme. How can I help?',
            interruptible: true,
            parameters: ['token' => signed_connection_token($call)], // <Parameter> children
        );
    }
}
```

```php
// In a service provider:
$this->app->bind(AiSessionHandler::class, ReceptionistHandler::class);
```

When `shouldHandle()` returns true, the default router returns an `ai` decision,
the inbound flow persists a `phone_ai_sessions` row (`status=started`), emits
`AiSessionStarted`, and returns the Conversation Relay TwiML.

## Session lifecycle

Point Conversation Relay's status callback at `POST /phone/twilio/ai/status`. On a
terminal callback the package updates the session and emits an event:

| Callback `SessionStatus` | Session `status` | Event |
| --- | --- | --- |
| `completed`, `ended`, `disconnected`, `stopped` | `ended` | `AiSessionEnded` |
| `failed`, `error` | `failed` | `AiSessionFailed` |

The update is idempotent: once a session has `ended_at`, retried callbacks do not
re-dispatch. `provider_session_sid` is stored when present.

## Building the runtime

`ConversationRelayConfig` carries everything the TwiML needs: `websocketUrl`,
`voice`, `language`, `welcomeGreeting`, `interruptible`, signed `parameters`, and
an `attributes` passthrough for any other Conversation Relay attribute.

The WebSocket server is a separate long-running process (Conversation Relay holds
one socket for the whole call — not a request/response webhook). Twilio's examples
are Node; in PHP you can use Ratchet/Swoole. For the per-turn LLM call,
[Prism](https://github.com/prism-php/prism) gives you a provider-agnostic,
env-keyed API:

```php
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;

$reply = Prism::text()
    ->using(Provider::Anthropic, 'claude-haiku-4-5') // voice = latency-sensitive
    ->withSystemPrompt('You are the receptionist for Acme Plumbing...')
    ->withPrompt($transcribedCallerUtterance)
    ->asText();
```

A first-party `fissible/phone-ai` companion packaging this runtime is planned;
until then it is host-app code.
