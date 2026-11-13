<?php

declare(strict_types=1);

namespace Tpetry\PostgresqlRls\Facades;

use Illuminate\Support\Facades\Facade;
use Tpetry\PostgresqlRls\RlsIdentityResolver as RlsIdentityResolverService;

/**
 * @method static void register(\Closure $provider)
 * @method static \Tpetry\PostgresqlRls\RlsIdentity resolve(array<string, mixed> $params)
 *
 * @see RlsIdentityResolverService
 */
class RlsIdentityResolver extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RlsIdentityResolverService::class;
    }
}
