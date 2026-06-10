<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class OptOutResult
{
    public const OPT_OUT = 'opt_out';

    public const OPT_IN = 'opt_in';

    public function __construct(
        public string $action,
        public string $keyword,
    ) {}

    public function isOptOut(): bool
    {
        return $this->action === self::OPT_OUT;
    }

    public function isOptIn(): bool
    {
        return $this->action === self::OPT_IN;
    }
}
