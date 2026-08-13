<?php

/**
 * Locales that are known to lag behind `en` and are exempt from the strict
 * parity assertions below. They are reported as skipped so the gap stays
 * visible, and `it('only lists genuinely incomplete locales …')` fails as soon
 * as one of them catches up, which forces it to be removed from this list.
 *
 * Key counts at the time of writing (out of 103):
 *   ar 61, cs 18, de 61, es 19, fa 17, he 61, it 19, nl 17, pt_BR 19, sk 19
 *
 * `ar` additionally drops the `:count` placeholder in
 * `bulk_retry_partial_description`; that is covered by the placeholder
 * assertion once the locale is completed and removed from this list.
 *
 * Translation contributions are very welcome — see the Contributing section of
 * the README.
 */
const INCOMPLETE_LOCALES = ['ar', 'cs', 'de', 'es', 'fa', 'he', 'it', 'nl', 'pt_BR', 'sk'];

const REFERENCE_LOCALE = 'en';

function translationLangPath(): string
{
    return dirname(__DIR__, 2).'/resources/lang';
}

/**
 * @return array<int, string>
 */
function translationLocales(): array
{
    return array_map('basename', glob(translationLangPath().'/*', GLOB_ONLYDIR) ?: []);
}

/**
 * @return array<string, string>
 */
function translationsFor(string $locale): array
{
    return require translationLangPath().'/'.$locale.'/translations.php';
}

/**
 * Placeholders Laravel replaces at runtime (`:count`, `:minutes`, `:delta`).
 * A missing or renamed one leaves the raw token in the rendered view.
 *
 * @return array<int, string>
 */
function translationPlaceholders(string $value): array
{
    preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $value, $matches);

    $placeholders = array_unique($matches[0]);
    sort($placeholders);

    return array_values($placeholders);
}

/**
 * Whether a string uses Laravel's pluralisation syntax (`{1} …|[2,*] …`).
 * The number of forms is deliberately not asserted: it is language-specific
 * (French uses 2, Polish uses 3).
 */
function translationIsPluralised(string $value): bool
{
    return (bool) preg_match('/\{\d+\}|\[\d+,(?:\d+|\*)\]/', $value);
}

dataset('complete locales', fn () => array_values(
    array_diff(translationLocales(), INCOMPLETE_LOCALES)
));

it('translates every key of the reference locale', function (string $locale) {
    $reference = translationsFor(REFERENCE_LOCALE);
    $translations = translationsFor($locale);

    $missing = array_diff(array_keys($reference), array_keys($translations));
    $orphan = array_diff(array_keys($translations), array_keys($reference));

    expect($missing)->toBe(
        [],
        sprintf('[%s] missing keys: %s', $locale, implode(', ', $missing))
    );

    expect($orphan)->toBe(
        [],
        sprintf('[%s] keys with no %s counterpart: %s', $locale, REFERENCE_LOCALE, implode(', ', $orphan))
    );
})->with('complete locales');

it('keeps the placeholders of the reference locale', function (string $locale) {
    $reference = translationsFor(REFERENCE_LOCALE);
    $translations = translationsFor($locale);

    foreach ($reference as $key => $value) {
        if (! array_key_exists($key, $translations)) {
            continue;
        }

        expect(translationPlaceholders($translations[$key]))->toBe(
            translationPlaceholders($value),
            sprintf('[%s] placeholder mismatch on "%s"', $locale, $key)
        );
    }
})->with('complete locales');

it('keeps the pluralisation syntax of the reference locale', function (string $locale) {
    $reference = translationsFor(REFERENCE_LOCALE);
    $translations = translationsFor($locale);

    foreach ($reference as $key => $value) {
        if (! translationIsPluralised($value) || ! array_key_exists($key, $translations)) {
            continue;
        }

        expect(translationIsPluralised($translations[$key]))->toBeTrue(
            sprintf('[%s] "%s" lost its pluralisation forms', $locale, $key)
        );
    }
})->with('complete locales');

it('only lists genuinely incomplete locales in INCOMPLETE_LOCALES', function () {
    $reference = array_keys(translationsFor(REFERENCE_LOCALE));
    $locales = translationLocales();

    foreach (INCOMPLETE_LOCALES as $locale) {
        expect(in_array($locale, $locales, true))->toBeTrue(
            sprintf('[%s] is listed in INCOMPLETE_LOCALES but has no translation file', $locale)
        );

        expect(array_diff($reference, array_keys(translationsFor($locale))))->not->toBeEmpty(
            sprintf('[%s] is complete: remove it from INCOMPLETE_LOCALES so it is asserted strictly', $locale)
        );
    }
});

if (INCOMPLETE_LOCALES !== []) {
    dataset('incomplete locales', fn () => INCOMPLETE_LOCALES);

    it('has locales left to translate', function (string $locale) {
        $reference = translationsFor(REFERENCE_LOCALE);

        expect(array_diff(array_keys($reference), array_keys(translationsFor($locale))))->toBe([]);
    })->with('incomplete locales')->skip('Known gap, tracked in INCOMPLETE_LOCALES.');
}
