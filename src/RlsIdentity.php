<?php

declare(strict_types=1);

namespace Tpetry\PostgresqlRls;

use InvalidArgumentException;

readonly class RlsIdentity
{
    /**
     * @param  array<string, scalar>  $claims
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public array $claims,
        public array $context = [],
    ) {
        foreach ($claims as $name => $value) {
            // @phpstan-ignore function.alreadyNarrowedType
            if (! is_string($name)) {
                throw new InvalidArgumentException("Invalid RLS claim '{$name}'.");
            }
            // @phpstan-ignore function.alreadyNarrowedType
            if (! is_scalar($value)) {
                throw new InvalidArgumentException("Invalid value for RLS claim '{$name}', it must be a scalar value.");
            }
        }
    }
}
