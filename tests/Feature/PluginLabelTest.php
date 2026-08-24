<?php

use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;

enum PluginLabelTestGroup: string
{
    case Operations = 'Operations';
}

/**
 * Register a string in the host application's JSON catalogue, the slot Laravel
 * uses for keys that carry neither a `::` namespace nor a `.` group.
 */
function registerJsonTranslation(string $key, string $value, string $locale = 'en'): void
{
    app('translator')->addLines(['*.'.$key => $value], $locale);
}

it('translates the namespaced keys the shipped config points at', function () {
    $plugin = FilamentJobsMonitorPlugin::make();

    expect(config('filament-jobs-monitor.resources.plural_label'))
        ->toBe('filament-jobs-monitor::translations.plural_model_label')
        ->and($plugin->getLabel())->toBe('Job')
        ->and($plugin->getPluralLabel())->toBe('Jobs')
        ->and($plugin->getNavigationGroup())->toBe('Settings');
});

it('translates a namespaced key configured by the user', function () {
    $plugin = FilamentJobsMonitorPlugin::make()
        ->label('filament-jobs-monitor::translations.navigation_label');

    expect($plugin->getLabel())->toBe('Jobs');
});

it('does not resolve a literal label through the host JSON catalogue', function () {
    registerJsonTranslation('Jobs', 'Something else entirely');

    $plugin = FilamentJobsMonitorPlugin::make()
        ->label('Jobs')
        ->pluralLabel('Jobs');

    expect(__('Jobs'))->toBe('Something else entirely')
        ->and($plugin->getLabel())->toBe('Jobs')
        ->and($plugin->getPluralLabel())->toBe('Jobs');
});

it('does not resolve a literal navigation group through the host JSON catalogue', function () {
    registerJsonTranslation('Settings', 'Réglages');

    $plugin = FilamentJobsMonitorPlugin::make()->navigationGroup('Settings');

    expect(__('Settings'))->toBe('Réglages')
        ->and($plugin->getNavigationGroup())->toBe('Settings');
});

it('leaves a unit enum navigation group untouched', function () {
    $plugin = FilamentJobsMonitorPlugin::make()
        ->navigationGroup(PluginLabelTestGroup::Operations);

    expect($plugin->getNavigationGroup())->toBe(PluginLabelTestGroup::Operations);
});

it('resolves a closure before translating it', function () {
    $plugin = FilamentJobsMonitorPlugin::make()
        ->label(fn () => 'filament-jobs-monitor::translations.model_label');

    expect($plugin->getLabel())->toBe('Job');
});
