<?php

declare(strict_types=1);

namespace Tpetry\PostgresqlRls;

use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class RlsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Rls::class);
        $this->app->singleton(RlsIdentityResolver::class);
    }

    public function boot(): void
    {
        Event::listen([TransactionCommitted::class, TransactionRolledBack::class, ConnectionEstablished::class], function (): void {
            $this->app->make(Rls::class)->handleMissedDeauthentication();
        });
    }
}
