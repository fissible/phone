<?php

declare(strict_types=1);

namespace Fissible\Phone\Events;

use Fissible\Phone\Models\PhoneTranscription;
use Fissible\Phone\Models\PhoneVoicemail;
use Fissible\Phone\Models\WebhookReceipt;

class TranscriptionStatusUpdated
{
    public function __construct(
        public readonly PhoneTranscription $transcription,
        public readonly ?string $oldStatus,
        public readonly string $newStatus,
        public readonly ?PhoneVoicemail $voicemail = null,
        public readonly ?WebhookReceipt $webhookReceipt = null,
    ) {}
}
