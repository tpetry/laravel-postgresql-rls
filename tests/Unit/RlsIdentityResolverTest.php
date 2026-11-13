<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Tpetry\PostgresqlRls\RlsIdentity;
use Tpetry\PostgresqlRls\RlsIdentityResolver;

it('resolves an empty identity without providers', function () {
    expect((new RlsIdentityResolver)->resolve([]))
        ->toEqual(new RlsIdentity([]));
});

it('merges the identities of all providers with later providers overwriting earlier keys', function () {
    $resolver = new RlsIdentityResolver;
    $resolver->register(fn () => new RlsIdentity(
        claims: [
            'user_id' => 1,
            'tenant_id' => 'acme',
        ],
        context: [
            'user' => 'alice',
            'tenant' => 'Acme Inc.',
        ],
    ));
    $resolver->register(fn () => new RlsIdentity(
        claims: [
            'tenant_id' => 'globex',
            'role' => 'admin',
        ],
        context: [
            'tenant' => 'Globex Corp.',
        ],
    ));

    expect($resolver->resolve([]))
        ->claims->toBe([
            'user_id' => 1,
            'tenant_id' => 'globex',
            'role' => 'admin',
        ])
        ->context->toBe([
            'user' => 'alice',
            'tenant' => 'Globex Corp.',
        ]);
});

it('passes the params and context to all providers', function () {
    $resolver = new RlsIdentityResolver;
    $resolver->register(fn (array $params, array $context) => new RlsIdentity(
        claims: Arr::prependKeysWith($params, 'first_'),
        context: Arr::prependKeysWith($context, 'first_'),
    ));
    $resolver->register(fn (array $params, array $context) => new RlsIdentity(
        claims: Arr::prependKeysWith($params, 'second_'),
        context: Arr::prependKeysWith($context, 'second_'),
    ));

    Context::addHidden('rls', ['role' => 'admin', 'region' => 'eu']);

    expect($resolver->resolve(['user_id' => 1, 'tenant_id' => 'acme']))
        ->claims->toBe([
            'first_user_id' => 1,
            'first_tenant_id' => 'acme',
            'second_user_id' => 1,
            'second_tenant_id' => 'acme',
        ])
        ->context->toBe([
            'first_role' => 'admin',
            'first_region' => 'eu',
            'second_role' => 'admin',
            'second_region' => 'eu',
        ]);
});
