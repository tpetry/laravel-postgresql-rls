<?php

declare(strict_types=1);

namespace Tpetry\PostgresqlRls;

use Closure;
use Illuminate\Support\Facades\Context;

class RlsIdentityResolver
{
    /**
     * @var list<Closure(array<string, mixed> $params, array<string, mixed> $context): RlsIdentity>
     */
    private array $providers = [];

    /**
     * Register a provider returning an RLS identity.
     *
     * @param  Closure(array<string, mixed> $params, array<string, mixed> $context): RlsIdentity  $provider
     */
    public function register(Closure $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Resolve the identity of all registered providers.
     *
     * @param  array<string, mixed>  $params
     */
    public function resolve(array $params): RlsIdentity
    {
        /** @var array<string, mixed> $context */
        $context = Context::getHidden('rls') ?? [];

        $resolvedClaims = [];
        $resolvedContext = [];
        foreach ($this->providers as $provider) {
            $identity = $provider($params, $context);
            $resolvedClaims = [...$resolvedClaims, ...$identity->claims];
            $resolvedContext = [...$resolvedContext, ...$identity->context];
        }

        return new RlsIdentity($resolvedClaims, $resolvedContext);
    }
}
