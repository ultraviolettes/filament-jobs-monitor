<?php

namespace Croustibat\FilamentJobsMonitor;

use Croustibat\FilamentJobsMonitor\Commands\PruneQueueMonitorCommand;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
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
            ->hasMigrations([
                'create_filament-jobs-monitor_table',
                'add_failures_to_filament-jobs-monitor_table',
            ])
            ->hasCommand(PruneQueueMonitorCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentJobsMonitorPlugin::class);
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            Css::make('filament-jobs-monitor-styles', __DIR__.'/../resources/dist/filament-jobs-monitor.css'),
        ], 'croustibat/filament-jobs-monitor');
    }
}
