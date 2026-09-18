<?php

use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;

it('registers the navigation by default', function () {
    expect(FilamentJobsMonitorPlugin::make()->shouldRegisterNavigation())->toBeTrue();
});

it('follows the resources.enabled config key when the fluent method is not called', function () {
    config()->set('filament-jobs-monitor.resources.enabled', false);

    expect(FilamentJobsMonitorPlugin::make()->shouldRegisterNavigation())->toBeFalse();
});

it('lets the fluent method take precedence over the config key', function (bool $config, bool|Closure $fluent, bool $expected) {
    config()->set('filament-jobs-monitor.resources.enabled', $config);

    expect(FilamentJobsMonitorPlugin::make()->enableNavigation($fluent)->shouldRegisterNavigation())->toBe($expected);
})->with([
    'config off, fluent on' => [false, true, true],
    'config on, fluent off' => [true, false, false],
    'config off, closure on' => [false, fn () => true, true],
    'config on, closure off' => [true, fn () => false, false],
]);
