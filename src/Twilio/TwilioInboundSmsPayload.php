<?php

declare(strict_types=1);

namespace Fissible\Phone\Twilio;

use Fissible\Phone\Exceptions\PhoneWebhookException;
use Illuminate\Http\Request;

class TwilioInboundSmsPayload
{
    /**
     * @param  list<array{url: string, content_type: string|null, index: int}>  $media
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $messageSid,
        public readonly ?string $smsSid,
        public readonly ?string $accountSid,
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $body,
        public readonly ?int $numSegments,
        public readonly array $media,
        public readonly array $metadata,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $messageSid = self::requiredString($request, 'MessageSid');
        $from = self::requiredString($request, 'From');
        $to = self::requiredString($request, 'To');
        $body = self::nullableString($request, 'Body');

        return new self(
            messageSid: $messageSid,
            smsSid: self::nullableString($request, 'SmsSid'),
            accountSid: self::nullableString($request, 'AccountSid'),
            from: $from,
            to: $to,
            body: $body === '' ? null : $body,
            numSegments: self::nullableInt($request, 'NumSegments'),
            media: self::media($request),
            metadata: self::metadata($request),
        );
    }

    private static function requiredString(Request $request, string $key): string
    {
        $value = self::nullableString($request, $key);

        if ($value === null || $value === '') {
            throw PhoneWebhookException::missingField($key);
        }

        return $value;
    }

    private static function nullableString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    private static function nullableInt(Request $request, string $key): ?int
    {
        $value = self::nullableString($request, $key);

        if ($value === null || $value === '' || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    /** @return list<array{url: string, content_type: string|null, index: int}> */
    private static function media(Request $request): array
    {
        $count = self::nullableInt($request, 'NumMedia') ?? 0;
        $media = [];

        for ($index = 0; $index < $count; $index++) {
            $url = self::nullableString($request, "MediaUrl{$index}");

            if ($url === null || $url === '') {
                continue;
            }

            $media[] = [
                'url' => $url,
                'content_type' => self::nullableString($request, "MediaContentType{$index}"),
                'index' => $index,
            ];
        }

        return $media;
    }

    /** @return array<string, mixed> */
    private static function metadata(Request $request): array
    {
        return [
            'twilio' => array_filter([
                'sms_sid' => self::nullableString($request, 'SmsSid'),
                'num_media' => self::nullableInt($request, 'NumMedia') ?? 0,
                'api_version' => self::nullableString($request, 'ApiVersion'),
            ], static fn ($value): bool => $value !== null),
        ];
    }
}
