<?php

declare(strict_types=1);

namespace Fissible\Phone\Support;

final class TranscriptionStatus
{
    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    /** @var array<string, int> */
    private const RANKS = [
        self::IN_PROGRESS => 1,
        self::COMPLETED => 2,
        self::FAILED => 2,
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
            self::FAILED,
        ];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::terminalStatuses(), true);
    }
}
