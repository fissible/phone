<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Collection;
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
 * @property ?int $phone_number_id
 * @property ?int $webhook_receipt_id
 * @property ?string $provider_call_sid
 * @property ?string $provider_parent_call_sid
 * @property ?string $provider_account_sid
 * @property ?int $provider_sequence_number
 * @property string $direction
 * @property string $from_number
 * @property string $to_number
 * @property string $status
 * @property int $status_rank
 * @property ?string $routing_mode
 * @property ?array $route_decision
 * @property ?string $answered_by
 * @property ?int $duration_seconds
 * @property ?Carbon $started_at
 * @property ?Carbon $answered_at
 * @property ?Carbon $ended_at
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PhoneNumber|null $phoneNumber
 * @property-read WebhookReceipt|null $webhookReceipt
 * @property-read Collection<int, PhoneRecording> $recordings
 * @property-read Collection<int, PhoneVoicemail> $voicemails
 * @property-read Collection<int, PhoneTranscription> $transcriptions
 */
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
