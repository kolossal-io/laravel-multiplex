<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\MultiplexServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

final class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Kolossal\\Multiplex\\Tests\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        $this->useDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            MultiplexServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
    }

    protected function useDatabase()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }
}
