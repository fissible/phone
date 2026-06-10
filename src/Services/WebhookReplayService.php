<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Models\WebhookReceipt;

class WebhookReplayService
{
    public function markForReplay(WebhookReceipt $receipt): WebhookReceipt
    {
        $receipt->forceFill([
            'processing_status' => 'pending',
            'failed_at' => null,
            'error_class' => null,
            'error_message' => null,
            'replay_count' => $receipt->replay_count + 1,
        ])->save();

        return $receipt->refresh();
    }
}
