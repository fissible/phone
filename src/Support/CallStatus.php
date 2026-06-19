<?php

declare(strict_types=1);

namespace Fissible\Phone\Support;

final class CallStatus
{
    public const QUEUED = 'queued';

    public const SENDING = 'sending';

    public const SEND_UNKNOWN = 'send_unknown';

    public const INITIATED = 'initiated';

    public const RINGING = 'ringing';

    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const BUSY = 'busy';

    public const FAILED = 'failed';

    public const NO_ANSWER = 'no_answer';

    public const CANCELED = 'canceled';

    public const MISSED = 'missed';

    private const RANKS = [
        self::INITIATED => 1,
        self::RINGING => 2,
        self::IN_PROGRESS => 3,
        self::COMPLETED => 4,
        self::BUSY => 4,
        self::FAILED => 4,
        self::NO_ANSWER => 4,
        self::CANCELED => 4,
        self::MISSED => 4,
    ];

    public static function rank(string $status): int
    {
        return self::RANKS[$status] ?? 0;
    }

    /** @return list<string> */
    public static function terminalStatuses(): array
    {
        return [
            self::COMPLETED,
            self::BUSY,
            self::FAILED,
            self::NO_ANSWER,
            self::CANCELED,
            self::MISSED,
        ];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::terminalStatuses(), true);
    }
}
