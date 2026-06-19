<?php

declare(strict_types=1);

namespace Fissible\Phone\Console\Commands;

use Fissible\Phone\Jobs\PruneWebhookReceipts;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;

class PhonePruneCommand extends Command
{
    protected $signature = 'phone:prune';

    protected $description = 'Prune expired webhook receipts and strip old raw payloads per retention config.';

    public function handle(Container $container): int
    {
        /** @var array{deleted: int, stripped: int} $result */
        $result = $container->call([new PruneWebhookReceipts, 'handle']);

        $this->info("Deleted {$result['deleted']} expired webhook receipt(s); stripped raw payloads from {$result['stripped']} receipt(s).");

        return self::SUCCESS;
    }
}
