<?php

declare(strict_types=1);

namespace Fissible\Phone\Console\Commands;

use Fissible\Phone\Twilio\TwilioClientFactory;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Throwable;

class PhoneDoctorCommand extends Command
{
    protected $signature = 'phone:doctor {--live : Make a live Twilio API request to verify credentials.}';

    protected $description = 'Inspect Fissible Phone configuration and webhook readiness.';

    /** @var list<string> */
    private array $failures = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(Repository $config, TwilioClientFactory $twilio): int
    {
        $this->failures = [];
        $this->warnings = [];

        $this->line('Fissible Phone doctor');

        $this->checkProvider($config);
        $this->checkTwilioCredentials($config);
        $this->checkSender($config);
        $this->checkWebhookUrl($config);
        $this->checkWebhookMiddleware($config);
        $this->checkVoice($config);

        if ($this->option('live')) {
            $this->checkLiveTwilio($config, $twilio);
        }

        $this->renderResults();

        return $this->failures === [] ? self::SUCCESS : self::FAILURE;
    }

    private function checkProvider(Repository $config): void
    {
        $provider = (string) $config->get('phone.provider', 'twilio');

        if ($provider !== 'twilio') {
            $this->recordFailure("Unsupported provider [{$provider}].");

            return;
        }

        $this->ok('Provider is twilio.');
    }

    private function checkTwilioCredentials(Repository $config): void
    {
        $accountSid = $this->string($config->get('phone.twilio.account_sid'));
        $authToken = $this->string($config->get('phone.twilio.auth_token'));

        if ($accountSid === null || $authToken === null) {
            $this->recordFailure('Twilio credentials are missing. Set TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN.');

            return;
        }

        if (! str_starts_with($accountSid, 'AC')) {
            $this->recordWarning('TWILIO_ACCOUNT_SID does not look like a Twilio Account SID.');

            return;
        }

        $this->ok('Twilio credentials are configured.');
    }

    private function checkSender(Repository $config): void
    {
        $messagingServiceSid = $this->string($config->get('phone.twilio.messaging_service_sid'));
        $defaultFrom = $this->string($config->get('phone.twilio.default_from'));

        if ($messagingServiceSid !== null) {
            $this->ok('Outbound SMS will use the configured Messaging Service SID.');

            return;
        }

        if ($defaultFrom !== null) {
            $this->ok('Outbound SMS will use the configured default from number.');

            return;
        }

        $this->recordWarning('No default SMS sender is configured. Set TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM before sending SMS.');
    }

    private function checkWebhookUrl(Repository $config): void
    {
        $baseUrl = $this->string($config->get('phone.webhooks.base_url'));

        if ($baseUrl === null) {
            $this->recordWarning('PHONE_WEBHOOK_BASE_URL is not configured. Set it in production before enabling Twilio signature validation behind a proxy.');

            return;
        }

        if (! str_starts_with($baseUrl, 'https://')) {
            $this->recordWarning('PHONE_WEBHOOK_BASE_URL should use https in production.');

            return;
        }

        $this->ok('Webhook base URL is configured.');
    }

    private function checkWebhookMiddleware(Repository $config): void
    {
        $middleware = $config->get('phone.webhooks.middleware', []);
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $middleware = array_map(static fn (mixed $value): string => (string) $value, $middleware);

        if (in_array('web', $middleware, true)) {
            $this->recordFailure('Webhook middleware includes [web]. Twilio routes must stay stateless and out of CSRF/session middleware.');

            return;
        }

        if (! in_array('phone.twilio', $middleware, true)) {
            $this->recordWarning('Webhook middleware does not include [phone.twilio], so Twilio signature validation/receipts may be bypassed.');

            return;
        }

        $this->ok('Webhook middleware is stateless.');
    }

    private function checkVoice(Repository $config): void
    {
        $mode = $this->string($config->get('phone.default_voice.mode')) ?? 'forward';
        $forwardTo = $this->string($config->get('phone.default_voice.forward_to'));

        if ($mode === 'forward' && $forwardTo === null) {
            $this->recordWarning('Default voice mode is forward, but PHONE_FORWARD_TO is not configured. Inbound calls will fall back to voicemail.');

            return;
        }

        $this->ok('Default voice routing is configured.');
    }

    private function checkLiveTwilio(Repository $config, TwilioClientFactory $twilio): void
    {
        $accountSid = $this->string($config->get('phone.twilio.account_sid'));

        if ($accountSid === null) {
            $this->recordFailure('Cannot run --live without TWILIO_ACCOUNT_SID.');

            return;
        }

        try {
            $twilio->make()->api->v2010->accounts($accountSid)->fetch();
            $this->ok('Live Twilio credential check succeeded.');
        } catch (Throwable $exception) {
            $this->recordFailure('Live Twilio credential check failed: '.$exception->getMessage());
        }
    }

    private function ok(string $message): void
    {
        $this->line('[OK] '.$message);
    }

    private function recordWarning(string $message): void
    {
        $this->warnings[] = $message;
        $this->line('[WARN] '.$message);
    }

    private function recordFailure(string $message): void
    {
        $this->failures[] = $message;
        $this->line('[FAIL] '.$message);
    }

    private function renderResults(): void
    {
        if ($this->failures !== []) {
            $this->newLine();
            $this->error(count($this->failures).' failure(s), '.count($this->warnings).' warning(s).');

            return;
        }

        if ($this->warnings !== []) {
            $this->newLine();
            $this->line('[WARN] '.count($this->warnings).' warning(s), no failures.');

            return;
        }

        $this->newLine();
        $this->info('No configuration issues found.');
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
