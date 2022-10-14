<?php

namespace Kolossal\Meta;

use Illuminate\Support\ServiceProvider;
use Kolossal\Meta\DataType\Registry;

class MetaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/meta.php' => config_path('meta.php'),
            ], 'config');
        }

        if (config('meta.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/meta.php', 'meta');

        $this->registerDataTypeRegistry();
    }

    /**
     * Add the DataType Registry to the service container.
     *
     * @copyright Plank Multimedia Inc.
     * @link https://github.com/plank/laravel-metable
     *
     * @return void
     */
    protected function registerDataTypeRegistry(): void
    {
        $this->app->singleton(Registry::class, function () {
            $registry = new Registry();

            foreach (config('meta.datatypes') as $handler) {
                $registry->addHandler(new $handler());
            }

            return $registry;
        });

        $this->app->alias(Registry::class, 'meta.datatype.registry');
    }
}
