<?php

namespace Kolossal\Meta\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Meta\MetaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Kolossal\\Meta\\Tests\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->useDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            MetaServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
    }

    protected function useDatabase()
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
