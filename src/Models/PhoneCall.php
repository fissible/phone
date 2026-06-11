<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneCall extends Model
{
    protected $table = 'phone_calls';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider_sequence_number' => 'integer',
            'status_rank' => 'integer',
            'route_decision' => 'array',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function webhookReceipt(): BelongsTo
    {
        return $this->belongsTo(WebhookReceipt::class, 'webhook_receipt_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(PhoneRecording::class, 'phone_call_id');
    }

    public function voicemails(): HasMany
    {
        return $this->hasMany(PhoneVoicemail::class, 'phone_call_id');
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(PhoneTranscription::class, 'phone_call_id');
    }
}
