<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneTranscription extends Model
{
    protected $table = 'phone_transcriptions';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status_rank' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(PhoneCall::class, 'phone_call_id');
    }

    public function recording(): BelongsTo
    {
        return $this->belongsTo(PhoneRecording::class, 'phone_recording_id');
    }

    public function voicemail(): BelongsTo
    {
        return $this->belongsTo(PhoneVoicemail::class, 'phone_voicemail_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function webhookReceipt(): BelongsTo
    {
        return $this->belongsTo(WebhookReceipt::class, 'webhook_receipt_id');
    }
}
