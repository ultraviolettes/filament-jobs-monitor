<?php

use Illuminate\Support\ServiceProvider;

/**
 * Every `vendor:publish --tag=...` command documented in the README has to keep
 * working. The tags are derived by spatie/laravel-package-tools from the package
 * name, so a rename of `FilamentJobsMonitorServiceProvider::$name`, a move of the
 * `config/` directory or an `export-ignore` entry in `.gitattributes` would
 * silently break the documented install steps. See issue #162.
 *
 * These assertions run on every Laravel x Filament pair of the CI matrix, which
 * is the point: the tag names are produced by framework code, not by ours.
 */
function publishGroup(string $tag): array
{
    expect(ServiceProvider::$publishGroups)->toHaveKey($tag);

    return ServiceProvider::$publishGroups[$tag];
}

it('registers every publish tag documented in the README', function (string $tag) {
    expect(publishGroup($tag))->not->toBeEmpty();
})->with([
    'filament-jobs-monitor-config',
    'filament-jobs-monitor-migrations',
    'filament-jobs-monitor-translations',
    'filament-jobs-monitor-views',
]);

it('publishes the config file shipped in the package to the application config path', function () {
    $paths = publishGroup('filament-jobs-monitor-config');

    // The provider registers the source as `src/../config/…`, so compare resolved paths.
    $sources = array_map('realpath', array_keys($paths));

    expect($sources)->toBe([dirname(__DIR__, 2).'/config/filament-jobs-monitor.php'])
        ->and(array_values($paths))->toBe([config_path('filament-jobs-monitor.php')]);
});

it('publishes both migrations', function () {
    $paths = publishGroup('filament-jobs-monitor-migrations');

    $published = array_values(array_map('basename', $paths));

    expect($published)->toHaveCount(2)
        ->and(implode(' ', $published))
        ->toContain('create_filament-jobs-monitor_table')
        ->toContain('add_failures_to_filament-jobs-monitor_table');
});
