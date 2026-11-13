<?php

declare(strict_types=1);

namespace Tpetry\PostgresqlRls;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;
use Tpetry\PostgresqlEnhanced\PostgresEnhancedConnection;
use Tpetry\PostgresqlRls\Facades\RlsIdentityResolver;
use WeakReference;

/**
 * RLS authentication is only supported for the default connection: Supporting multiple connections is intentionally not
 * done: The identity's context is stored as one hidden Laravel context. Deauthenticating one connection would remove
 * the context for all connections, and inheriting the context to e.g. queue jobs would no longer work. Maybe connection
 * specific contexts could work, but it is a vastly more complex implementation where I can't know currently if there
 * would be some issues. Implement it yourself when needed!
 *
 * Note: The context is only added AFTER the transaction settings have been changed and removed BEFORE changing them, so
 * a failing database query (e.g. in an aborted transaction) will not keep a stray Laravel context in memory.
 */
class Rls
{
    /**
     * @var WeakReference<Connection>|null
     */
    private ?WeakReference $authenticatedConnection = null;

    private int $authenticatedLevel = 0;

    private static bool $strict = false;

    /**
     * Throw an exception when a missed deauthentication is detected.
     */
    public static function shouldBeStrict(bool $value = true): void
    {
        self::$strict = $value;
    }

    /**
     * Run the callback within a lazy authenticated transaction.
     *
     * @template TReturn
     *
     * @param  Closure(RlsIdentity $identity): TReturn  $callback
     * @param  array<string, mixed>  $params
     * @return TReturn
     */
    public function authenticated(Closure $callback, array $params = []): mixed
    {
        /** @var PostgresEnhancedConnection $connection */
        $connection = DB::connection();

        return $connection->transactionLazy(function () use ($callback, $params): mixed {
            $identity = $this->authenticateTransaction($params);

            try {
                $result = $callback($identity);
            } catch (Throwable $exception) {
                rescue(fn () => $this->deauthenticateTransaction(), report: false);

                throw $exception;
            }

            $this->deauthenticateTransaction();

            return $result;
        });
    }

    /**
     * Authenticate the currently running transaction.
     *
     * @param  array<string, mixed>  $params
     */
    public function authenticateTransaction(array $params = []): RlsIdentity
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() === 0) {
            throw new LogicException('RLS authentication must be changed within a transaction.');
        }

        return tap(RlsIdentityResolver::resolve($params), function (RlsIdentity $identity) use ($connection): void {
            $claims = json_encode($identity->claims, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);

            // @phpstan-ignore argument.type
            $connection->unprepared("SET LOCAL rls.claims = {$connection->escape($claims)}");

            $this->authenticatedConnection = WeakReference::create($connection);
            $this->authenticatedLevel = $connection->transactionLevel();
            Context::addHidden('rls', $identity->context);
        });
    }

    /**
     * Remove the RLS claims of the currently running transaction and the context.
     */
    public function deauthenticateTransaction(): void
    {
        $this->authenticatedConnection = null;
        Context::forgetHidden('rls');
        DB::connection()->unprepared("SET LOCAL rls.claims = '{}'");
    }

    /**
     * Deauthenticate an authentication that has not been properly deauthenticated.
     *
     * An authentication ends with the transaction or savepoint it has been activated in. But PostgreSQL only removes
     * the local settings when the transaction ends. A released savepoint keeps the values for the outer transaction. A
     * purged or disconnected connection should also remove the Laravel context. This safe mode handler should
     * deactivate the auth in all cases to ensure an authenticated RLS scope can't leak.
     *
     * @internal
     */
    public function handleMissedDeauthentication(): void
    {
        if ($this->authenticatedConnection === null) {
            return;
        }

        $connection = $this->authenticatedConnection->get();
        if ($connection !== null && $connection->transactionLevel() >= $this->authenticatedLevel) {
            return;
        }

        $this->authenticatedConnection = null;
        Context::forgetHidden('rls');

        if ($connection !== null && $connection->transactionLevel() > 0) {
            rescue(fn () => $connection->unprepared("SET LOCAL rls.claims = '{}'"), report: false);
        }

        if (self::$strict) {
            throw new LogicException('RLS authentication was not deauthenticated before its transaction ended.');
        }
    }
}
