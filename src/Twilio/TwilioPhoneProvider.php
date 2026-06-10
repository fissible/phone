<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Contracts\PhoneProvider;
use Fissible\Phone\Exceptions\PhoneConfigurationException;
use Fissible\Phone\ValueObjects\OutboundMessage;
use Fissible\Phone\ValueObjects\ProviderMessage;
use Illuminate\Contracts\Config\Repository;

class TwilioPhoneProvider implements PhoneProvider
{
    public function __construct(
        private readonly TwilioClientFactory $clientFactory,
        private readonly Repository $config,
    ) {}

    public function sendMessage(OutboundMessage $message): ProviderMessage
    {
        $options = $this->messageOptions($message);

        $twilioMessage = $this->clientFactory
            ->make()
            ->messages
            ->create($message->to, $options);

        return new ProviderMessage(
            provider: 'twilio',
            providerMessageSid: $twilioMessage->sid,
            status: (string) $twilioMessage->status,
            raw: [
                'sid' => $twilioMessage->sid,
                'status' => (string) $twilioMessage->status,
                'to' => $twilioMessage->to,
                'from' => $twilioMessage->from,
                'messaging_service_sid' => $twilioMessage->messagingServiceSid,
                'error_code' => $twilioMessage->errorCode,
                'error_message' => $twilioMessage->errorMessage,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function messageOptions(OutboundMessage $message): array
    {
        $options = [];

        if ($message->body !== null && $message->body !== '') {
            $options['body'] = $message->body;
        }

        if ($message->mediaUrls !== []) {
            $options['mediaUrl'] = $message->mediaUrls;
        }

        if ($message->statusCallbackUrl !== null && $message->statusCallbackUrl !== '') {
            $options['statusCallback'] = $message->statusCallbackUrl;
        }

        $messagingServiceSid = $message->messagingServiceSid
            ?: $this->config->get('phone.twilio.messaging_service_sid');

        if (is_string($messagingServiceSid) && $messagingServiceSid !== '') {
            $options['messagingServiceSid'] = $messagingServiceSid;

            return $options;
        }

        $from = $message->from ?: $this->config->get('phone.twilio.default_from');

        if (! is_string($from) || $from === '') {
            throw PhoneConfigurationException::missingSender();
        }

        $options['from'] = $from;

        return $options;
    }
}
