<?php

namespace kolossal\MetaRevision\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use kolossal\MetaRevision\MetaRevisionServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'kolossal\\MetaRevision\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            MetaRevisionServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_laravel-meta-revision_table.php.stub';
        $migration->up();
        */
    }
}
