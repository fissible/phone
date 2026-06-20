<?php

declare(strict_types=1);

namespace Fissible\Phone\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_type
 * @property ?string $provider_sid
 * @property string $request_method
 * @property string $request_url
 * @property string $request_hash
 * @property ?string $source_ip
 * @property bool $signature_valid
 * @property ?array $headers
 * @property ?array $payload
 * @property string $processing_status
 * @property ?Carbon $processed_at
 * @property ?Carbon $failed_at
 * @property ?string $error_class
 * @property ?string $error_message
 * @property int $replay_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
