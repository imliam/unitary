<?php

declare(strict_types=1);

namespace Unitary\Tests\Laravel;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Unitary\Laravel\UnitaryServiceProvider;
use Unitary\Unitary;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [UnitaryServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('unitary.exchange_rates.provider', 'fixed');
        $app['config']->set('unitary.exchange_rates.fixed', ['base' => 'USD', 'rates' => ['GBP' => '0.8', 'EUR' => '0.9']]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->decimal('weight', 30, 10)->nullable();
            $table->decimal('height_value', 30, 15)->nullable();
            $table->string('height_unit')->nullable();
            $table->decimal('price_amount', 20, 4)->nullable();
            $table->string('price_currency')->nullable();
            $table->decimal('charge_value', 30, 15)->nullable();
            $table->string('charge_unit')->nullable();
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Unitary::setConverter(null);
    }
}
