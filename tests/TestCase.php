<?php

declare(strict_types=1);

namespace Tpetry\PostgresqlRls\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tpetry\PostgresqlEnhanced\PostgresqlEnhancedServiceProvider;
use Tpetry\PostgresqlRls\RlsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PostgresqlEnhancedServiceProvider::class,
            RlsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'pgsql');
    }
}
