<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Twilio\Security\RequestValidator;

class TwilioWebhookValidator
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function publicUrlFor(Request $request): string
    {
        $baseUrl = $this->config->get('phone.webhooks.base_url');

        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/').$request->getRequestUri();
        }

        return $request->fullUrl();
    }

    public function validate(Request $request, string $url): bool
    {
        $authToken = $this->config->get('phone.twilio.auth_token');

        if (! is_string($authToken) || $authToken === '') {
            throw PhoneConfigurationException::missingTwilioCredentials();
        }

        $signature = (string) $request->headers->get('X-Twilio-Signature', '');

        return (new RequestValidator($authToken))->validate(
            $signature,
            $url,
            $this->payloadForValidation($request, $url),
        );
    }

    /** @return array<string, mixed>|string */
    private function payloadForValidation(Request $request, string $url): array|string
    {
        if ($this->usesBodyHash($url)) {
            return $request->getContent();
        }

        return $request->request->all();
    }

    private function usesBodyHash(string $url): bool
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);

        parse_str($query, $parameters);

        return array_key_exists('bodySHA256', $parameters);
    }
}
