<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\MultiplexServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use function Orchestra\Testbench\workbench_path;

final class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Kolossal\\Multiplex\\Tests\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            MultiplexServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }
}
