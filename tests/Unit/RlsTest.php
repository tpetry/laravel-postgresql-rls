<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Pest\Expectation;
use Tpetry\PostgresqlRls\Facades\Rls;
use Tpetry\PostgresqlRls\Facades\RlsIdentityResolver;
use Tpetry\PostgresqlRls\RlsIdentity;

beforeEach(fn () => Rls::shouldBeStrict(false));

describe('authenticated', function () {
    it('runs the callback authenticated', function () {
        RlsIdentityResolver::register(fn (array $params) => new RlsIdentity(
            claims: ['tenant_id' => $params['tenant']],
            context: ['tenant' => 'Acme Inc.'],
        ));

        $result = Rls::authenticated(function (): string {
            expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
                ->toBe('{"tenant_id":"acme"}');
            expect(Context::getHidden('rls'))
                ->toBe([
                    'tenant' => 'Acme Inc.',
                ]);

            return 'result';
        }, ['tenant' => 'acme']);

        expect($result)
            ->toBe('result');
        expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
            ->toBeEmpty();
        expect(Context::hasHidden('rls'))
            ->toBeFalse();
    });

    it('deauthenticates when the callback fails', function () {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme'],
            context: ['tenant' => 'Acme Inc.'],
        ));

        expect(fn () => Rls::authenticated(fn () => throw new RuntimeException('failed')))
            ->toThrow(RuntimeException::class, 'failed');
        expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
            ->toBeEmpty();
        expect(Context::hasHidden('rls'))
            ->toBeFalse();
    });
});

describe('authenticateTransaction', function () {
    it('requires a running transaction', function () {
        Rls::authenticateTransaction();
    })->throws(LogicException::class, 'RLS authentication must be changed within a transaction.');

    it('returns the resolved identity', function () {
        RlsIdentityResolver::register(fn (array $params) => new RlsIdentity(
            claims: ['tenant_id' => $params['tenant']],
            context: ['tenant' => 'Acme Inc.'],
        ));

        DB::connection()->transaction(function () {
            expect(Rls::authenticateTransaction(['tenant' => 'acme']))
                ->claims->toBe([
                    'tenant_id' => 'acme',
                ])
                ->context->toBe([
                    'tenant' => 'Acme Inc.',
                ]);
        });
    });

    it('passes the params and the current context to the providers', function () {
        RlsIdentityResolver::register(fn (array $params, array $context) => new RlsIdentity(
            claims: $params,
            context: $context,
        ));

        DB::connection()->transaction(function () {
            Context::addHidden('rls', [
                'tenant' => 'Acme Inc.',
            ]);

            expect(Rls::authenticateTransaction(['tenant_id' => 'acme']))
                ->claims->toBe([
                    'tenant_id' => 'acme',
                ])
                ->context->toBe([
                    'tenant' => 'Acme Inc.',
                ]);
        });
    });

    it('applies the identity', function () {
        RlsIdentityResolver::register(fn (array $params) => new RlsIdentity(
            claims: $params,
            context: $params,
        ));

        DB::connection()->transaction(function () {
            Rls::authenticateTransaction(['tenant_id' => 'acme', 'user_id' => 42]);

            expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
                ->toBe('{"tenant_id":"acme","user_id":42}');
            expect(Context::getHidden('rls'))
                ->toBe([
                    'tenant_id' => 'acme',
                    'user_id' => 42,
                ]);
        });
    });

    it('replaces an active authentication when authenticating again', function () {
        RlsIdentityResolver::register(fn (array $params) => new RlsIdentity(
            claims: $params,
            context: $params,
        ));

        DB::connection()->transaction(function () {
            Rls::authenticateTransaction(['tenant_id' => 'acme']);
            Rls::authenticateTransaction(['user_id' => 1]);

            expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
                ->toBe('{"user_id":1}');
            expect(Context::getHidden('rls'))
                ->toBe([
                    'user_id' => 1,
                ]);
        });
    });
});

describe('deauthenticateTransaction', function () {
    it('removes settings and the context', function () {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme', 'user_id' => 1],
            context: ['tenant' => 'Acme Inc.'],
        ));

        DB::connection()->transaction(function () {
            Rls::authenticateTransaction();
            Rls::deauthenticateTransaction();

            expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
                ->toBe('{}');
            expect(Context::hasHidden('rls'))
                ->toBeFalse();
        });
    });
});

describe('safe mode', function () {
    function expectThrowsWhenStrict(bool $strict, Closure $callback): void
    {
        expect($callback)
            ->when($strict, fn (Expectation $expectation) => $expectation->toThrow(LogicException::class, 'RLS authentication was not deauthenticated before its transaction ended.'))
            ->unless($strict, fn (Expectation $expectation) => $expectation->not->toThrow(LogicException::class));
    }

    dataset('strict', [
        'safe mode' => [false],
        'strict mode' => [true],
    ]);

    it('deauthenticates when the transaction is committed', function (bool $strict) {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme'],
            context: ['tenant' => 'Acme Inc.'],
        ));
        Rls::shouldBeStrict($strict);

        DB::connection()->beginTransaction();
        Rls::authenticateTransaction();
        expectThrowsWhenStrict($strict, fn () => DB::connection()->commit());

        expect(Context::hasHidden('rls'))
            ->toBeFalse();
    })->with('strict');

    it('deauthenticates when the transaction is rolled back', function (bool $strict) {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme'],
            context: ['tenant' => 'Acme Inc.'],
        ));
        Rls::shouldBeStrict($strict);

        DB::connection()->beginTransaction();
        Rls::authenticateTransaction();
        expectThrowsWhenStrict($strict, fn () => DB::connection()->rollBack());

        expect(Context::hasHidden('rls'))
            ->toBeFalse();
    })->with('strict');

    it('deauthenticates when the savepoint is committed', function (bool $strict) {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme'],
            context: ['tenant' => 'Acme Inc.'],
        ));
        Rls::shouldBeStrict($strict);

        DB::connection()->beginTransaction();
        DB::connection()->beginTransaction();
        Rls::authenticateTransaction();
        expectThrowsWhenStrict($strict, fn () => DB::connection()->commit());

        expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
            ->toBe('{}');
        expect(Context::hasHidden('rls'))
            ->toBeFalse();

        DB::connection()->rollBack();
    })->with('strict');

    it('deauthenticates when the savepoint is rolled back', function (bool $strict) {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme'],
            context: ['tenant' => 'Acme Inc.'],
        ));
        Rls::shouldBeStrict($strict);

        DB::connection()->beginTransaction();
        DB::connection()->beginTransaction();
        Rls::authenticateTransaction();
        expectThrowsWhenStrict($strict, fn () => DB::connection()->rollBack());

        expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
            ->toBe('{}');
        expect(Context::hasHidden('rls'))
            ->toBeFalse();

        DB::connection()->rollBack();
    })->with('strict');

    it('deauthenticates when the connection is replaced', function (bool $strict) {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme'],
            context: ['tenant' => 'Acme Inc.'],
        ));
        Rls::shouldBeStrict($strict);

        DB::connection()->beginTransaction();
        Rls::authenticateTransaction();
        DB::purge();
        expectThrowsWhenStrict($strict, fn () => DB::connection());

        expect(Context::hasHidden('rls'))
            ->toBeFalse();
    })->with('strict');

    it('does not deauthenticate when a nested savepoint ends', function (bool $strict) {
        RlsIdentityResolver::register(fn () => new RlsIdentity(
            claims: ['tenant_id' => 'acme'],
            context: ['tenant' => 'Acme Inc.'],
        ));
        Rls::shouldBeStrict($strict);

        DB::connection()->beginTransaction();
        Rls::authenticateTransaction();
        DB::connection()->beginTransaction();
        DB::connection()->commit();

        expect(DB::connection()->scalar("SELECT current_setting('rls.claims', true)"))
            ->toBe('{"tenant_id":"acme"}');
        expect(Context::getHidden('rls'))
            ->toBe([
                'tenant' => 'Acme Inc.',
            ]);
    })->with('strict');
});
