<?php

declare(strict_types=1);

namespace Fissible\Phone\Console\Commands;

use Illuminate\Console\Command;

class PhoneInstallCommand extends Command
{
    protected $signature = 'phone:install {--force : Overwrite any existing published files}';

    protected $description = 'Publish Fissible Phone config and migrations and print setup next steps.';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'phone-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->callSilently('vendor:publish', [
            '--tag' => 'phone-migrations',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Fissible Phone installed: published config/phone.php and migrations.');
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and a sender (TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM).');
        $this->line('  2. Set PHONE_WEBHOOK_BASE_URL to your public https URL if behind a proxy.');
        $this->line('  3. Run `php artisan migrate`.');
        $this->line('  4. Point your Twilio number webhooks at the /phone/twilio/* routes.');
        $this->line('  5. Run `php artisan phone:doctor` to verify configuration.');

        return self::SUCCESS;
    }
}
