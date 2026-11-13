<div align="center">
    <h1>Laravel PostgreSQL Row-Level Security</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/tpetry/laravel-postgresql-rls"><img src="https://img.shields.io/packagist/v/tpetry/laravel-postgresql-rls.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/tpetry/laravel-postgresql-rls"><img src="https://img.shields.io/packagist/php-v/tpetry/laravel-postgresql-rls.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/tpetry/laravel-postgresql-rls"><img src="https://badge.laravel.cloud/badge/tpetry/laravel-postgresql-rls?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/tpetry/laravel-postgresql-rls/actions"><img src="https://img.shields.io/github/actions/workflow/status/tpetry/laravel-postgresql-rls/tests.yml?branch=main&label=Tests&style=flat-square" alt="GitHub Workflow Status (main)"></a>
    <a href="https://packagist.org/packages/tpetry/laravel-postgresql-rls"><img src="https://img.shields.io/packagist/dt/tpetry/laravel-postgresql-rls.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Row-level security for Laravel applications using PostgreSQL

## Installation

You can install the package via Composer:

```bash
composer require tpetry/laravel-postgresql-rls
```

## Usage

Register an identity resolver, e.g. in the `boot()` method of your `AppServiceProvider`, which returns the claims for the RLS policies:

```php
use Illuminate\Support\Facades\Auth;
use Tpetry\PostgresqlRls\Facades\RlsIdentityResolver;
use Tpetry\PostgresqlRls\RlsIdentity;

RlsIdentityResolver::register(fn (array $params, array $context) => new RlsIdentity(
    claims: ['tenant_id' => Auth::user()->tenant_id],
));
```

All queries within the `authenticated()` closure are executed within a transaction with those claims applied:

```php
use Tpetry\PostgresqlRls\Facades\Rls;

$invoices = Rls::authenticated(function () {
    return Invoice::all();
});
```

The claims are available to your RLS policies as a JSON object in the `rls.claims` setting, e.g. for a policy created with the [row-level security migrations](https://github.com/tpetry/laravel-postgresql-enhanced#row-level-security):

```php
use Tpetry\PostgresqlEnhanced\Schema\Blueprint;
use Tpetry\PostgresqlEnhanced\Support\Facades\Schema;

Schema::table('invoices', function (Blueprint $table) {
    $table->rowLevelSecurity();
    $table->createPolicy(
        name: 'tenant_isolation',
        user: 'app',
        condition: "tenant_id = (SELECT NULLIF(current_setting('rls.claims', true), '')::jsonb ->> 'tenant_id')::bigint",
    );
});
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## License

Laravel PostgreSQL Row-Level Security is open-sourced software licensed under the [MIT license](LICENSE.md).
