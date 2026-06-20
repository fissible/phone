<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope_key
 * @property ?string $scope_type
 * @property ?string $scope_id
 * @property string $provider
 * @property ?int $phone_call_id
 * @property ?int $phone_recording_id
 * @property ?int $phone_number_id
 * @property ?int $webhook_receipt_id
 * @property ?string $from_number
 * @property ?string $to_number
 * @property string $status
 * @property ?string $recording_url
 * @property ?int $duration_seconds
 * @property ?string $transcription_text
 * @property ?Carbon $received_at
 * @property ?Carbon $listened_at
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PhoneVoicemail extends Model
{
    protected $table = 'phone_voicemails';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'received_at' => 'datetime',
            'listened_at' => 'datetime',
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

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function webhookReceipt(): BelongsTo
    {
        return $this->belongsTo(WebhookReceipt::class, 'webhook_receipt_id');
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(PhoneTranscription::class, 'phone_voicemail_id');
    }
}
