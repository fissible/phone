<?php

declare(strict_types=1);

namespace Fissible\Phone;

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\Twilio\TwilioClientFactory;
use Fissible\Phone\Twilio\TwilioPhoneProvider;
use Illuminate\Support\ServiceProvider;

class PhoneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/phone.php', 'phone');

        $this->app->singleton(TwilioClientFactory::class);

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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/phone.php' => config_path('phone.php'),
            ], 'phone-config');
        }
    }
}
