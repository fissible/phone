# Voicemail

When a call routes to voicemail (no forward destination, after-hours, or an
unanswered `<Dial>`), the package returns TwiML that plays a greeting and records
the caller, with a recording status callback tagged `purpose=voicemail`.

## Recording

`POST /phone/twilio/voice/recording` creates a `phone_recordings` row. A
`phone_voicemails` row is created **only** when the callback is tagged
`purpose=voicemail`, so future QA/compliance recordings can share the recordings
table without being treated as customer voicemails. `VoicemailReceived` dispatches
after persistence (idempotent — provider retries do not duplicate the voicemail).

Greeting precedence: the number's `voicemail_greeting`, then
`default_voice.voicemail_greeting`, then a built-in default.

## Transcription

Opt in:

```env
PHONE_TRANSCRIBE_VOICEMAILS=true
```

The voicemail `<Record>` then includes a `transcribeCallback` to
`POST /phone/twilio/voice/transcription`. Transcription callbacks create
`phone_transcriptions`; a completed voicemail transcription also updates the
matching `phone_voicemails.transcription_text`. `TranscriptionStatusUpdated`
dispatches on each update.

> Twilio's built-in transcription is best-effort and English-oriented. For higher
> quality, store the recording URL and transcribe with your own pipeline.

## Notifying a team

Bind `Fissible\Phone\Contracts\TeamNotifier` to be notified of new voicemails
(`voicemail.received`). Keep notifiers fast inside webhook requests; hand slow
delivery to a queued job. See [the contracts table](README.md).

## Media retention

Twilio recording/MMS media URLs may require authentication and are not retained
forever. If you need long-term storage, fetch and store media in your own storage
before Twilio's retention window expires. See [Compliance](compliance.md).
