<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PhoneRecording extends Model
{
    protected $table = 'phone_recordings';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status_rank' => 'integer',
            'duration_seconds' => 'integer',
            'channels' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(PhoneCall::class, 'phone_call_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function webhookReceipt(): BelongsTo
    {
        return $this->belongsTo(WebhookReceipt::class, 'webhook_receipt_id');
    }

    public function voicemail(): HasOne
    {
        return $this->hasOne(PhoneVoicemail::class, 'phone_recording_id');
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(PhoneTranscription::class, 'phone_recording_id');
    }
}
