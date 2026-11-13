<?php

declare(strict_types=1);

use Tpetry\PostgresqlRls\RlsIdentity;

it('accepts valid input', function () {
    $identity = new RlsIdentity(
        claims: [
            'tenant_id' => 'acme',
            'user_id' => 1,
            'admin' => true,
        ],
        context: [
            'tenant' => 'Acme Inc.',
        ],
    );

    expect($identity)
        ->claims->toBe([
            'tenant_id' => 'acme',
            'user_id' => 1,
            'admin' => true,
        ])
        ->context->toBe([
            'tenant' => 'Acme Inc.',
        ]);
});

it('accepts any string as claim name', function () {
    expect(new RlsIdentity(['tenant.id' => 1, '1tenant' => 2, 'tenant-id' => 3, "it's" => 4]))
        ->claims->toBe([
            'tenant.id' => 1,
            '1tenant' => 2,
            'tenant-id' => 3,
            "it's" => 4,
        ]);
});

it('rejects claim names that are not strings', function () {
    expect(fn () => new RlsIdentity(['first'])) // @phpstan-ignore argument.type
        ->toThrow(InvalidArgumentException::class, "Invalid RLS claim '0'.");
});

it('rejects invalid claim values', function () {
    expect(fn () => new RlsIdentity(['tenant_id' => null])) // @phpstan-ignore argument.type
        ->toThrow(InvalidArgumentException::class, "Invalid value for RLS claim 'tenant_id', it must be a scalar value.");
    expect(fn () => new RlsIdentity(['tenant_id' => ['acme']])) // @phpstan-ignore argument.type
        ->toThrow(InvalidArgumentException::class, "Invalid value for RLS claim 'tenant_id', it must be a scalar value.");
    expect(fn () => new RlsIdentity(['tenant_id' => new stdClass])) // @phpstan-ignore argument.type
        ->toThrow(InvalidArgumentException::class, "Invalid value for RLS claim 'tenant_id', it must be a scalar value.");
});
