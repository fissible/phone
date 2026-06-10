<?php

declare(strict_types=1);

namespace Fissible\Phone\Support;

final class MessageStatus
{
    public const DRAFT = 'draft';

    public const QUEUED = 'queued';

    public const SENDING = 'sending';

    public const SEND_UNKNOWN = 'send_unknown';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const UNDELIVERED = 'undelivered';

    public const FAILED = 'failed';

    public const RECEIVED = 'received';

    public const IGNORED = 'ignored';

    /** @var array<string, int> */
    private const RANKS = [
        self::DRAFT => 0,
        self::RECEIVED => 0,
        self::QUEUED => 1,
        self::SENDING => 2,
        self::SEND_UNKNOWN => 3,
        self::SENT => 5,
        self::DELIVERED => 6,
        self::UNDELIVERED => 7,
        self::FAILED => 7,
        self::IGNORED => 7,
    ];

    public static function rank(string $status): int
    {
        return self::RANKS[$status] ?? 0;
    }
}
