<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
