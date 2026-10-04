# Contributing

Contributions are **welcome** and will be fully **credited**.

Please read and understand the contribution guide before creating an issue or pull request. By participating, you agree to abide by the [Code of Conduct](CODE_OF_CONDUCT.md).

## Etiquette

This project is open source, and as such, the maintainers give their free time to build and maintain the source code
held within. They make the code freely available in the hope that it will be of use to other developers. It would be
extremely unfair for them to suffer abuse or anger for their hard work.

Please be considerate towards maintainers when raising issues or presenting pull requests. Let's show the
world that developers are civilized and selfless people.

It's the duty of the maintainer to ensure that all submissions to the project are of sufficient
quality to benefit the project. Many developers have different skillsets, strengths, and weaknesses. Respect the maintainer's decision, and do not be upset or abusive if your submission is not used.

## Viability

When requesting or submitting new features, first consider whether it might be useful to others. Open
source projects are used by many developers, who may have entirely different needs to your own. Think about
whether or not your feature is likely to be used by other users of the project.

## Procedure

Before filing an issue:

- Attempt to replicate the problem, to ensure that it wasn't a coincidental incident.
- Check to make sure your feature suggestion isn't already present within the project.
- Check the pull requests tab to ensure that the bug doesn't have a fix in progress.
- Check the pull requests tab to ensure that the feature isn't already in progress.

Before submitting a pull request:

- Check the codebase to ensure that your feature doesn't already exist.
- Check the pull requests to ensure that another person hasn't already submitted the feature or fix.

## Requirements

If the project maintainer has any additional requirements, you will find them listed here.

- **Code style** - The project uses [Laravel Pint](https://laravel.com/docs/pint). Run `composer lint` before pushing.

- **Static analysis** - `composer analyse` runs PHPStan (Larastan) and must stay clean. Do not add entries to `phpstan-baseline.neon` to silence new errors.

- **Add tests!** - Your patch won't be accepted if it doesn't have tests. Run them with `composer test`.

- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](https://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.

## Building the assets

The compiled stylesheet under `resources/dist/` is committed, so rebuild it with `npm run build`
(never the Tailwind CLI on its own) whenever you touch a Blade view or a class name in `src/`.

Tailwind 4 is configured from `resources/css/plugin.css` — there is no `tailwind.config.js`. The
colour utilities are declared with `@theme inline` against Filament's own variables (`--primary-600`,
`--gray-800`, …), so the stylesheet follows the colours configured on the panel. Keep it that way:
hardcoding a palette here would make the plugin ignore the host panel's theme.

`npm run build` is deterministic: rebuilding an unchanged checkout must leave
`resources/dist/filament-jobs-monitor.css` byte for byte identical, and the `Build assets` workflow
fails otherwise. Nothing in the build may fetch anything over the network (the old `filament-purge`
step downloaded Filament's stylesheet from GitHub at build time, which is why it was removed — see
issue #168).

The build ends with an `npm run layer` step that wraps the output in `@layer components`. This is
not cosmetic: the stylesheet is registered globally through `FilamentAsset`, so it loads on every
page of every panel of the host application, and unlayered CSS outranks anything inside
`@layer utilities` — where Filament emits the app's own utilities — regardless of source order.
Shipping a bare `.hidden` is enough to break `hidden md:block` app-wide (see #145).
The same step also prepends `@layer properties,theme,base,components,utilities;`. `@filamentStyles`
prints plugin stylesheets before the panel theme, so this file is parsed first, and the first time a
layer is named fixes its rank: without that statement `components` would rank below `base`, and
Tailwind's preflight would strip the padding and margins of every Filament component.
`tests/Feature/PluginStylesheetTest.php` fails if the committed build ever loses its layer or that
statement, or if the statement drifts from the order Filament's own theme creates its layers in.

**Happy coding**!
