<?php

declare(strict_types=1);

namespace Tpetry\PostgresqlRls\Facades;

use Illuminate\Support\Facades\Facade;
use Tpetry\PostgresqlRls\Rls as RlsService;

/**
 * @method static TReturn authenticated<TReturn>(\Closure(\Tpetry\PostgresqlRls\RlsIdentity $identity): TReturn $callback, array<string, mixed> $params = [])
 * @method static \Tpetry\PostgresqlRls\RlsIdentity authenticateTransaction(array<string, mixed> $params = [])
 * @method static void deauthenticateTransaction()
 * @method static void shouldBeStrict(bool $value = true)
 *
 * @see RlsService
 */
class Rls extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RlsService::class;
    }
}
