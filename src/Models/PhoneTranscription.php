<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $scope_key
 * @property ?string $scope_type
 * @property ?string $scope_id
 * @property string $provider
 * @property ?int $phone_call_id
 * @property ?int $phone_recording_id
 * @property ?int $phone_voicemail_id
 * @property ?int $phone_number_id
 * @property ?int $webhook_receipt_id
 * @property ?string $provider_transcription_sid
 * @property ?string $provider_recording_sid
 * @property ?string $provider_call_sid
 * @property ?string $provider_account_sid
 * @property ?string $purpose
 * @property string $status
 * @property int $status_rank
 * @property ?string $transcription_text
 * @property ?string $transcription_url
 * @property ?string $error_code
 * @property ?string $error_message
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PhoneCall|null $call
 * @property-read PhoneRecording|null $recording
 * @property-read PhoneVoicemail|null $voicemail
 * @property-read PhoneNumber|null $phoneNumber
 * @property-read WebhookReceipt|null $webhookReceipt
 */
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
