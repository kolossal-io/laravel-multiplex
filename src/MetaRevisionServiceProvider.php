<?php

namespace kolossal\MetaRevision;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use kolossal\MetaRevision\Commands\MetaRevisionCommand;

class MetaRevisionServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-meta-revision')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-meta-revision_table')
            ->hasCommand(MetaRevisionCommand::class);
    }
}
