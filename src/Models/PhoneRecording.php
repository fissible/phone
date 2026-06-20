<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope_key
 * @property ?string $scope_type
 * @property ?string $scope_id
 * @property string $provider
 * @property ?int $phone_call_id
 * @property ?int $phone_number_id
 * @property ?int $webhook_receipt_id
 * @property ?string $provider_recording_sid
 * @property ?string $provider_call_sid
 * @property ?string $provider_account_sid
 * @property ?string $purpose
 * @property string $status
 * @property int $status_rank
 * @property ?string $recording_url
 * @property ?int $duration_seconds
 * @property ?int $channels
 * @property ?string $source
 * @property ?string $track
 * @property ?string $error_code
 * @property ?string $error_message
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
