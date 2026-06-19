<?php

declare(strict_types=1);

namespace Fissible\Phone\Console\Commands;

use Fissible\Phone\Models\WebhookReceipt;
use Fissible\Phone\Services\WebhookReplayService;
use Illuminate\Console\Command;

class PhoneWebhookReplayCommand extends Command
{
    protected $signature = 'phone:webhook:replay {receipt : The phone_webhook_receipts id to reprocess}';

    protected $description = 'Reprocess a stored Twilio webhook receipt through its matching processor.';

    public function handle(WebhookReplayService $replays): int
    {
        $id = $this->argument('receipt');

        $receipt = WebhookReceipt::query()->find($id);

        if (! $receipt instanceof WebhookReceipt) {
            $this->error("Webhook receipt [{$id}] not found.");

            return self::FAILURE;
        }

        $receipt = $replays->replay($receipt);

        $this->info("Replayed webhook receipt [{$receipt->id}] ({$receipt->event_type}); status is now {$receipt->processing_status}.");

        return self::SUCCESS;
    }
}
