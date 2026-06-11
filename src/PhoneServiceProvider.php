<?php

declare(strict_types=1);

namespace Fissible\Phone;

use Fissible\Phone\Console\Commands\PhoneDoctorCommand;
use Fissible\Phone\Contracts\CallRouter;
use Fissible\Phone\Contracts\MessagePolicy;
use Fissible\Phone\Contracts\OptOutPolicy;
use Fissible\Phone\Contracts\PhoneNumberResolver;
use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Contracts\ScopeResolver;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\Http\Middleware\ValidateTwilioWebhook;
use Fissible\Phone\Services\DefaultCallRouter;
use Fissible\Phone\Services\DefaultMessagePolicy;
use Fissible\Phone\Services\DefaultOptOutPolicy;
use Fissible\Phone\Services\DefaultPhoneNumberResolver;
use Fissible\Phone\Services\DefaultScopeResolver;
use Fissible\Phone\Services\WebhookReceiptRecorder;
use Fissible\Phone\Twilio\TwilioClientFactory;
use Fissible\Phone\Twilio\TwilioPhoneProvider;
use Fissible\Phone\Twilio\TwilioWebhookValidator;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class PhoneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/phone.php', 'phone');

        $this->app->singleton(TwilioClientFactory::class);
        $this->app->singleton(TwilioWebhookValidator::class);
        $this->app->singleton(WebhookReceiptRecorder::class);

        $this->app->bind(PhoneNumberResolver::class, DefaultPhoneNumberResolver::class);
        $this->app->bind(ScopeResolver::class, DefaultScopeResolver::class);
        $this->app->bind(CallRouter::class, DefaultCallRouter::class);
        $this->app->bind(MessagePolicy::class, DefaultMessagePolicy::class);
        $this->app->bind(OptOutPolicy::class, DefaultOptOutPolicy::class);

        $this->app->bind(PhoneProvider::class, function ($app): PhoneProvider {
            $provider = (string) $app['config']->get('phone.provider', 'twilio');

            return match ($provider) {
                'twilio' => $app->make(TwilioPhoneProvider::class),
                default => throw PhoneConfigurationException::unsupportedProvider($provider),
            };
        });

        $this->app->singleton(PhoneManager::class, fn ($app): PhoneManager => new PhoneManager($app));
        $this->app->alias(PhoneManager::class, 'phone');
    }

    public function boot(): void
    {
        if ($this->app->bound('router')) {
            $this->app->make(Router::class)
                ->aliasMiddleware('phone.twilio', ValidateTwilioWebhook::class);
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/twilio.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PhoneDoctorCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/phone.php' => config_path('phone.php'),
            ], 'phone-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'phone-migrations');
        }
    }
}
