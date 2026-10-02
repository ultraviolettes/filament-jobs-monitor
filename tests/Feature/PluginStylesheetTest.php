<?php

/**
 * The plugin stylesheet is registered globally through `FilamentAsset::register()`, so it
 * loads on every page of every panel of the host application. Unlayered CSS always beats
 * rules sitting inside a cascade layer, whatever the source order — and Filament emits the
 * app's own utilities inside `@layer utilities`. A single bare `.hidden` shipped here is
 * therefore enough to break `hidden md:block` app-wide. See issue #145.
 *
 * The layer alone is not enough: `@filamentStyles` prints plugin stylesheets before the panel
 * theme, so this file is parsed first and its first layer sets the cascade order. Without an
 * explicit statement, `components` would be created before `base` and Tailwind's preflight
 * (`* { margin: 0; padding: 0 }`) would then strip every `fi-*` component of the panel.
 *
 * These assertions run against the committed build output, so they fail if the stylesheet
 * is ever rebuilt without the `layer` step of `npm run build:styles`.
 */
const LAYER_ORDER = '@layer properties,theme,base,components,utilities;';

function pluginStylesheetPath(): string
{
    return dirname(__DIR__, 2).'/resources/dist/filament-jobs-monitor.css';
}

/**
 * The stylesheet without its leading layer order statement.
 */
function pluginStylesheetRules(): string
{
    $css = trim((string) file_get_contents(pluginStylesheetPath()));

    return str_starts_with($css, LAYER_ORDER) ? substr($css, strlen(LAYER_ORDER)) : $css;
}

/**
 * Everything that follows the first balanced `{ … }` block of the stylesheet.
 * Whatever is left over lives outside the cascade layer.
 */
function cssAfterFirstBlock(string $css): string
{
    $depth = 0;
    $length = strlen($css);

    for ($i = 0; $i < $length; $i++) {
        if ($css[$i] === '{') {
            $depth++;
        } elseif ($css[$i] === '}' && --$depth === 0) {
            return trim(substr($css, $i + 1));
        }
    }

    return trim($css);
}

it('ships the stylesheet registered by the service provider', function () {
    expect(pluginStylesheetPath())->toBeReadableFile();
});

it('declares the Tailwind layer order before any rule', function () {
    $css = trim((string) file_get_contents(pluginStylesheetPath()));

    expect($css)->toStartWith(LAYER_ORDER);
});

it('declares the layers in the order Filament creates them', function () {
    $theme = (string) file_get_contents(dirname(__DIR__, 2).'/vendor/filament/filament/dist/theme.css');

    preg_match_all('/@layer ([a-z-]+)\s*\{/', $theme, $matches);

    expect(LAYER_ORDER)->toBe('@layer '.implode(',', array_unique($matches[1])).';');
});

it('wraps the whole stylesheet in the components cascade layer', function () {
    // `components` specifically: Tailwind declares `@layer theme, base, components, utilities`,
    // so joining an already declared layer keeps the plugin below the app's utilities. A layer
    // named after the plugin would be created after `utilities` and would outrank it instead.
    expect(pluginStylesheetRules())->toStartWith('@layer components{');
});

it('leaves no rule outside the cascade layer', function () {
    expect(cssAfterFirstBlock(pluginStylesheetRules()))->toBe('');
});
