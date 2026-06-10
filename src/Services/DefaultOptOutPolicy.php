<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\OptOutPolicy;
use Fissible\Phone\Models\PhoneMessage;
use Fissible\Phone\Models\PhoneThread;
use Fissible\Phone\ValueObjects\OptOutResult;
use Illuminate\Contracts\Config\Repository;

class DefaultOptOutPolicy implements OptOutPolicy
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function applyInbound(PhoneThread $thread, PhoneMessage $message): ?OptOutResult
    {
        if (! (bool) $this->config->get('phone.sms.opt_out.enabled', true)) {
            return null;
        }

        $keyword = $this->normalizedKeyword($message->body);

        if ($keyword === null) {
            return null;
        }

        if (in_array($keyword, $this->keywords('phone.sms.opt_out.stop_keywords'), true)) {
            return $this->optOut($thread, $message, $keyword);
        }

        if (in_array($keyword, $this->keywords('phone.sms.opt_out.start_keywords'), true)) {
            return $this->optIn($thread, $message, $keyword);
        }

        return null;
    }

    private function optOut(PhoneThread $thread, PhoneMessage $message, string $keyword): ?OptOutResult
    {
        if ($thread->opted_out_at !== null) {
            return null;
        }

        $thread->forceFill([
            'opted_out_at' => now(),
            'metadata' => $this->metadata($thread, $message, OptOutResult::OPT_OUT, $keyword),
        ])->save();

        return new OptOutResult(OptOutResult::OPT_OUT, $keyword);
    }

    private function optIn(PhoneThread $thread, PhoneMessage $message, string $keyword): ?OptOutResult
    {
        if ($thread->opted_out_at === null) {
            return null;
        }

        $thread->forceFill([
            'opted_out_at' => null,
            'metadata' => $this->metadata($thread, $message, OptOutResult::OPT_IN, $keyword),
        ])->save();

        return new OptOutResult(OptOutResult::OPT_IN, $keyword);
    }

    /** @return array<string, mixed> */
    private function metadata(PhoneThread $thread, PhoneMessage $message, string $action, string $keyword): array
    {
        return array_replace($thread->metadata ?? [], [
            'opt_out' => [
                'action' => $action,
                'keyword' => $keyword,
                'message_id' => $message->getKey(),
                'message_sid' => $message->provider_message_sid,
                'at' => now()->toJSON(),
            ],
        ]);
    }

    private function normalizedKeyword(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $keyword = preg_replace('/\s+/', ' ', trim($body));

        if (! is_string($keyword) || $keyword === '') {
            return null;
        }

        return strtoupper($keyword);
    }

    /** @return list<string> */
    private function keywords(string $key): array
    {
        $keywords = $this->config->get($key, []);

        if (! is_array($keywords)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $keyword): ?string => is_string($keyword) && trim($keyword) !== ''
                    ? strtoupper(trim($keyword))
                    : null,
                $keywords,
            ),
        ));
    }
}
