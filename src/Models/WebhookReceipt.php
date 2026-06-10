<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class WebhookReceipt extends Model
{
    protected $table = 'phone_webhook_receipts';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'headers' => 'array',
            'payload' => 'array',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'replay_count' => 'integer',
        ];
    }

    public function markProcessed(): void
    {
        if ($this->processing_status === 'processed') {
            return;
        }

        $this->forceFill([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'failed_at' => null,
            'error_class' => null,
            'error_message' => null,
        ])->save();
    }

    public function markFailed(Throwable $exception): void
    {
        $this->forceFill([
            'processing_status' => 'failed',
            'failed_at' => now(),
            'error_class' => $exception::class,
            'error_message' => $exception->getMessage(),
        ])->save();
    }
}
