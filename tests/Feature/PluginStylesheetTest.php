<?php

/**
 * The plugin stylesheet is registered globally through `FilamentAsset::register()`, so it
 * loads on every page of every panel of the host application. Unlayered CSS always beats
 * rules sitting inside a cascade layer, whatever the source order — and Filament emits the
 * app's own utilities inside `@layer utilities`. A single bare `.hidden` shipped here is
 * therefore enough to break `hidden md:block` app-wide. See issue #145.
 *
 * These assertions run against the committed build output, so they fail if the stylesheet
 * is ever rebuilt without the `layer` step of `npm run build:styles`.
 */
function pluginStylesheetPath(): string
{
    return dirname(__DIR__, 2).'/resources/dist/filament-jobs-monitor.css';
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

it('wraps the whole stylesheet in the components cascade layer', function () {
    $css = trim((string) file_get_contents(pluginStylesheetPath()));

    // `components` specifically: Tailwind declares `@layer theme, base, components, utilities`,
    // so joining an already declared layer keeps the plugin below the app's utilities. A layer
    // named after the plugin would be created after `utilities` and would outrank it instead.
    expect($css)->toStartWith('@layer components{');
});

it('leaves no rule outside the cascade layer', function () {
    $css = trim((string) file_get_contents(pluginStylesheetPath()));

    expect(cssAfterFirstBlock($css))->toBe('');
});
