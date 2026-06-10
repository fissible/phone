<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Twilio\Rest\Client;

class TwilioClientFactory
{
    public function __construct(private readonly Repository $config) {}

    public function make(): Client
    {
        $sid = $this->config->get('phone.twilio.account_sid');
        $token = $this->config->get('phone.twilio.auth_token');

        if (! is_string($sid) || $sid === '' || ! is_string($token) || $token === '') {
            throw PhoneConfigurationException::missingTwilioCredentials();
        }

        return new Client($sid, $token);
    }
}
