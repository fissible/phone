<?php

declare(strict_types=1);

namespace Fissible\Phone\Support;

final class RecordingStatus
{
    public const IN_PROGRESS = 'in_progress';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const ABSENT = 'absent';

    public const FAILED = 'failed';

    /** @var array<string, int> */
    private const RANKS = [
        self::IN_PROGRESS => 1,
        self::PROCESSING => 2,
        self::COMPLETED => 3,
        self::ABSENT => 3,
        self::FAILED => 3,
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
            self::ABSENT,
            self::FAILED,
        ];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::terminalStatuses(), true);
    }
}
