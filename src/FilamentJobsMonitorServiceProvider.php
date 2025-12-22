<?php

namespace Croustibat\FilamentJobsMonitor;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentJobsMonitorServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-jobs-monitor';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_filament-jobs-monitor_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentJobsMonitorPlugin::class);
    }
}
