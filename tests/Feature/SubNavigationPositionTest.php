<?php

use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Filament\Pages\Enums\SubNavigationPosition;

it('puts the sub-navigation on top by default', function () {
    expect(FilamentJobsMonitorPlugin::make()->getSubNavigationPosition())
        ->toBe(SubNavigationPosition::Top);
});

it('reads the config key', function (SubNavigationPosition|string $configured, SubNavigationPosition $expected) {
    config()->set('filament-jobs-monitor.resources.sub_navigation_position', $configured);

    expect(FilamentJobsMonitorPlugin::make()->getSubNavigationPosition())->toBe($expected);
})->with([
    'enum' => [SubNavigationPosition::Start, SubNavigationPosition::Start],
    'string' => ['end', SubNavigationPosition::End],
]);

it('lets the plugin method override the config key', function () {
    config()->set('filament-jobs-monitor.resources.sub_navigation_position', SubNavigationPosition::Start);

    expect(FilamentJobsMonitorPlugin::make()->subNavigationPosition(SubNavigationPosition::Top)->getSubNavigationPosition())
        ->toBe(SubNavigationPosition::Top);
});

it('accepts a closure', function () {
    expect(FilamentJobsMonitorPlugin::make()->subNavigationPosition(fn () => SubNavigationPosition::End)->getSubNavigationPosition())
        ->toBe(SubNavigationPosition::End);
});
