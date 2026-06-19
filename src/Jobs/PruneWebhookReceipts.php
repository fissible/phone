<?php

declare(strict_types=1);

namespace Fissible\Phone\Jobs;

use Fissible\Phone\Models\WebhookReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class PruneWebhookReceipts implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Delete expired receipts and strip raw payloads from mid-aged receipts
     * per the `phone.retention` configuration.
     *
     * @return array{deleted: int, stripped: int}
     */
    public function handle(Repository $config): array
    {
        $receiptDays = (int) $config->get('phone.retention.webhook_receipts_days', 90);
        $payloadDays = (int) $config->get('phone.retention.raw_payload_days', 30);

        $deleted = 0;
        $stripped = 0;

        if ($receiptDays > 0) {
            $deleted = WebhookReceipt::query()
                ->where('created_at', '<', now()->subDays($receiptDays))
                ->delete();
        }

        if ($payloadDays > 0) {
            $stripped = WebhookReceipt::query()
                ->where('created_at', '<', now()->subDays($payloadDays))
                ->where(static function (Builder $query): void {
                    $query->whereNotNull('payload')->orWhereNotNull('headers');
                })
                ->update(['payload' => null, 'headers' => null]);
        }

        return ['deleted' => $deleted, 'stripped' => $stripped];
    }
}
