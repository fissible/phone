<?php

declare(strict_types=1);

namespace Fissible\Phone\ValueObjects;

final readonly class Scope
{
    public function __construct(
        public string $key = 'global',
        public ?string $type = null,
        public ?string $id = null,
    ) {}
}
