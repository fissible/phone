<?php

declare(strict_types=1);

namespace Fissible\Phone\Support;

final class CallStatus
{
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
}
