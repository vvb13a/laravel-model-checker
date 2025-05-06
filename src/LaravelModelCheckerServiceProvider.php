<?php

namespace Vvb13a\LaravelModelChecker;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vvb13a\LaravelModelChecker\Commands\CheckModelCommand;
use Vvb13a\LaravelModelChecker\Commands\CheckModelsCommand;

class LaravelModelCheckerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-model-checker')
            ->hasConfigFile()
            ->hasCommand(CheckModelCommand::class)
            ->hasCommand(CheckModelsCommand::class)
            ->discoversMigrations()
            ->runsMigrations();
    }
}