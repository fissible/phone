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
 * @property ?int $phone_thread_id
 * @property ?int $phone_number_id
 * @property ?int $webhook_receipt_id
 * @property ?string $provider_message_sid
 * @property ?string $provider_account_sid
 * @property string $direction
 * @property ?string $from_number
 * @property string $to_number
 * @property ?string $body
 * @property ?array $media
 * @property ?int $num_segments
 * @property string $status
 * @property int $status_rank
 * @property ?string $error_code
 * @property ?string $error_message
 * @property ?Carbon $queued_at
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $failed_at
 * @property ?Carbon $received_at
 * @property ?array $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PhoneThread|null $thread
 * @property-read PhoneNumber|null $phoneNumber
 * @property-read WebhookReceipt|null $webhookReceipt
 */
class PhoneMessage extends Model
{
    protected $table = 'phone_messages';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'media' => 'array',
            'num_segments' => 'integer',
            'status_rank' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(PhoneThread::class, 'phone_thread_id');
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
