<?php

declare(strict_types=1);

namespace Fissible\Phone\Services;

use Fissible\Phone\Contracts\ScopeResolver;
use Fissible\Phone\ValueObjects\Scope;
use Illuminate\Contracts\Config\Repository;

class DefaultScopeResolver implements ScopeResolver
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function resolve(): Scope
    {
        return new Scope(
            key: (string) $this->config->get('phone.numbers.default_scope_key', 'global'),
            type: $this->nullableString($this->config->get('phone.numbers.default_scope_type')),
            id: $this->nullableString($this->config->get('phone.numbers.default_scope_id')),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
