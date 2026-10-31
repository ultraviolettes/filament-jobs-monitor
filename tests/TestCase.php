<?php

namespace Croustibat\FilamentJobsMonitor\Tests;

use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorServiceProvider;
use Filament\FilamentServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // SQLite in memory dies with the connection, so every test opens on an
        // empty schema and creates the tables it needs. A server-backed
        // connection keeps them, and the suite would then create them twice.
        if (config('database.connections.testing.driver') !== 'sqlite') {
            Schema::dropAllTables();
        }

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Croustibat\\FilamentJobsMonitor\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            FilamentJobsMonitorServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Several tests name the `testing` connection directly, so a MySQL run
        // repoints that name rather than switching the default one.
        if (env('DB_CONNECTION') === 'mysql') {
            config()->set('database.connections.testing', config('database.connections.mysql'));
        }

        /*
        $migration = include __DIR__.'/../database/migrations/create_filament-jobs-monitor_table.php.stub';
        $migration->up();
        */
    }
}
