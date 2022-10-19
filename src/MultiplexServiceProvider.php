<?php

namespace Kolossal\Multiplex;

use Illuminate\Support\ServiceProvider;
use Kolossal\Multiplex\DataType\Registry;

class MultiplexServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/multiplex.php' => config_path('multiplex.php'),
            ], 'config');
        }

        if (config('multiplex.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/multiplex.php', 'multiplex');

        $this->registerDataTypeRegistry();
    }

    /**
     * Add the DataType Registry to the service container.
     *
     * @copyright Plank Multimedia Inc.
     *
     * @link https://github.com/plank/laravel-metable
     *
     * @return void
     */
    protected function registerDataTypeRegistry(): void
    {
        $this->app->singleton(Registry::class, function () {
            $registry = new Registry();

            foreach (config('multiplex.datatypes') as $handler) {
                $registry->addHandler(new $handler());
            }

            return $registry;
        });

        $this->app->alias(Registry::class, 'multiplex.datatype.registry');
    }
}
