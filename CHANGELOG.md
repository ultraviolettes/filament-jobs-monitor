# Changelog

All notable changes to `filament-jobs-monitor` will be documented in this file.

## 4.6.0 - 2026-08-24

### Added

- **Polish translations for the Failures page**: the 42 keys missing from `pl` (failure groups, stack-trace viewer, failure stats widgets) are now translated, bringing `pl` to full parity with `en`. ([@webard](https://github.com/webard) — #131)
- **Translation parity test**: `TranslationParityTest` asserts that every locale matches `en` key for key, keeps the same `:count` / `:minutes` / `:delta` placeholders, and keeps every pluralised string pluralised — in either of Laravel's notations, the explicit `{1} …|[2,*] …` intervals or the implicit `one|few|many` positional forms. How many forms a locale declares stays language-specific and is not asserted. Locales still behind — `ar`, `cs`, `de`, `es`, `fa`, `he`, `it`, `nl`, `pt_BR`, `sk` — are listed in an `INCOMPLETE_LOCALES` constant and reported as skipped; the test fails both when a covered locale drifts and when a listed one becomes complete, so the list has to shrink.
- **Russian translations**: a complete `ru` locale, in full parity with `en`. ([@saythe0](https://github.com/saythe0) — #146)
- **Translated "Clear logs" action**: the action label, confirmation modal (heading, description, submit button) and success notification were hardcoded English strings; they now go through the translation catalogue as `clear_logs`, `clear_logs_heading`, `clear_logs_description`, `clear_logs_confirm` and `logs_cleared`. Closes #139. ([@saythe0](https://github.com/saythe0) — #146)
- **Localised resource labels and navigation group**: `label`, `plural_label` and `navigation_group` now default to translation keys instead of hardcoded English, so they follow the panel locale. ([@saythe0](https://github.com/saythe0) — #146)
- **French and Polish translations** for the six keys added above (`model_label` and the five `clear_logs` / `logs_cleared` ones), keeping `fr` and `pl` in full parity with `en`.

### Changed

- **`navigation_group` keeps its `Settings` wording**: the key is now actually rendered (it previously existed in the locale files but was never read — the config shipped a hardcoded `'Settings'`). Its `en` value moved from `System` to `Settings`, and every locale was realigned on the local wording for *Settings*, so panels that never published the config keep the navigation group they have always shown, and now get it localised.

### Fixed

- **Configured labels no longer collide with the host JSON catalogue**: `label`, `plural_label` and `navigation_group` are only resolved through `__()` when they can address a translation file. A plain string such as `'Jobs'` carries neither a `::` namespace nor a `.` group, so Laravel would look it up in the application's `resources/lang/{locale}.json` and an unrelated entry there could silently rewrite a label configured by the user; such values are now used verbatim.
- **Plugin stylesheet no longer overrides the host application's Tailwind utilities**: `resources/dist/filament-jobs-monitor.css` is registered globally through `FilamentAsset`, so it loads on every page of every panel. It shipped bare utilities (`.block`, `.flex`, `.hidden`, `.w-full`, …) outside any cascade layer, and unlayered CSS outranks rules inside `@layer utilities` regardless of source order — which is where Filament emits the app's own utilities. The most visible symptom was `hidden md:block` staying hidden at every viewport. The build output is now wrapped in `@layer components`, so the app's utilities win again. ([@gmagnenat](https://github.com/gmagnenat) — #145)

## 4.5.0 - 2026-07-01

### Added

- **Sentry-style Failures page**: failed jobs are grouped by signature (exception class + job class + normalised message), with a stats overview, per-group 7-day sparklines, Open/Resolved/All tabs, a stack-trace detail slide-over, and actions to resolve/reopen/retry a whole group. Requires the new `add_failures_to_filament-jobs-monitor_table` migration; if it isn't run the plugin keeps working and simply skips grouping. (#119)
- **Dedicated pruning command** `php artisan filament-jobs-monitor:prune`: prunes the package's `QueueMonitor` records directly, since Laravel's `model:prune` only auto-discovers models under `app/Models`. ([@luiseduardobraschi](https://github.com/luiseduardobraschi) — #120)
- **Tenant ID discovery for queued events/listeners**: tenant detection now also covers `CallQueuedListener`, with a configurable `tenancy.payload_key` (default `tenant_id`). ([@zerdotre](https://github.com/zerdotre) — #115)
- **PHP 8.5 support**: added to the Composer constraint, with a Carbon deprecation fixed. ([@BjornKraft](https://github.com/BjornKraft) — #123)

### Fixed

- **Stats widget on SQLite/MySQL**: the execution-time aggregates (total/average) now convert datetimes to epoch seconds per driver (`strftime` on SQLite, `UNIX_TIMESTAMP` on MySQL/MariaDB, `EXTRACT(EPOCH …)` on PostgreSQL) instead of a raw datetime subtraction that returned `0` on SQLite. ([@mokhosh](https://github.com/mokhosh) — #55)
- **N+1 queries in the stats widget**: the per-day counters now run a single query and group in memory, which is also portable across database drivers. ([@webard](https://github.com/webard) — #121)
- **`prunable()` crash when pruning is disabled**: it returned `false`, which broke `pruneAll()` (`Call to a member function when() on false`); it now returns a query that matches nothing (safe no-op). (#120)
- **Duplicate config key**: removed the duplicated `sub_navigation_position` entry. ([@DanielFatkic](https://github.com/DanielFatkic) — #118)

### CI

- Add a `run-tests` workflow running Pest on PHP 8.4 and 8.5. (#125)
- Bump `actions/checkout` from 6 to 7. (#122)

## 4.4.1 - 2026-04-20

### Fixed

- **Details action modal crash**: Fixed `Attempt to read property "exception_message" on null` error when clicking the "Details" action on `QueueMonitorResource`. The closure parameter was renamed from `$queueMonitor` to `$record` to match Filament 5 conventions. ([@danielebarbaro](https://github.com/danielebarbaro) — #111)

### CI

- Bump `dependabot/fetch-metadata` from `3.0.0` to `3.1.0` (#112)
- Fix Dependabot auto-merge workflow: replace `--auto --merge` with `--squash` to work without the auto-merge repository setting

## 4.4.0 - 2026-04-13

### Added

- **`int|string` tenant ID support**: `scopeForTenant()` now accepts both integer and string tenant IDs, enabling compatibility with packages like `tenancyforlaravel` that use string-based UUIDs as tenant identifiers. The PHP payload serialization query has been updated accordingly. ([@zerdotre](https://github.com/zerdotre) — #106)
- **Clear logs button**: New "Clear all logs" header action in the queue monitor table, with a confirmation modal before truncating all records. ([@zerdotre](https://github.com/zerdotre) — #106)
- **Tenant ID column visibility**: The `tenant_id` column is now visible in the table when multi-tenancy is enabled in the config.

### Changed

- Migration stub: `tenant_id` column type changed from `unsignedBigInteger` to `string` (with index). This only affects **new installations** — existing users who need this change should create a new migration to alter the column type.

> **Note for existing multi-tenant users**: If you were using integer-based tenant IDs, the serialization format used in `scopeForTenant()` has changed from PHP integer format (`i:123;`) to PHP string format (`s:3:"123";`). Existing records in the `queue_monitors` table are not affected, but new jobs dispatched with string-typed `$tenantId` will be stored and queried using the string format.

## 3.0.0 - 2025-10-29

### Breaking Changes

This release adds support for Filament v4, which includes several breaking changes from Filament v3. Please see [UPGRADE.md](UPGRADE.md) for a complete migration guide.

- **Filament v4 Compatibility**: Updated minimum Filament version requirement from `^3.0` to `^4.0`
- **Form/Schema API**: Changed `form(Form $form)` method signature to `form(Schema $schema)` following Filament v4 conventions
- **Action Namespace**: Moved action imports from `Filament\Tables\Actions` to `Filament\Actions` namespace
- **Page Actions**: Renamed `getActions()` to `getHeaderActions()` in ListRecords pages (visibility changed from public to protected)
- **Widget Methods**: Renamed `getCards()` to `getStats()` in StatsOverviewWidget classes

### Added

- Added `HasNavigation` trait to resources for better navigation handling
- Added `sub_navigation_position` configuration option to customize sub-navigation placement (Top or Sidebar)
- Added comprehensive [UPGRADE.md](UPGRADE.md) migration guide

### Changed

- Updated all imports to use Filament v4 namespace structure
- Updated README with version 3.x compatibility information
- Enhanced configuration file with additional options and documentation

## 2.3.0 - 2024-03-26

https://github.com/croustibat/filament-jobs-monitor/releases/tag/2.3.0

## 2.2.0 - 2024-02-15

https://github.com/croustibat/filament-jobs-monitor/releases/tag/2.2.0

## 2.1.0 - 2023-12-08

https://github.com/croustibat/filament-jobs-monitor/releases/tag/2.1.0

## 2.0.0 - 2023-08-09

- Add support for Filament v3
- Implement a configurable panel plugin class
- Make table columns sortable
- Add a configurable navigation menu resource sort order
- Add a toggle to show a job count badge in the navigation menu
- Add a configuration option to customize the QueueMonitorResource model
- Split the resource label into singular and plural

## 1.4.0 - 2023-08-09

- Fix : apply sortable to Table (not Forms)
- Apply sortable on all columns
- Added getNavigationSort to Resource and config, and changed getNavigationGroup to work in default installation of package.

## 1.3.0 - 2023-07-13

- Jobs are sorted by started date from the most recent to the oldest
- New language file for spanish

https://github.com/croustibat/filament-jobs-monitor/releases/tag/1.3.0
Thanks to the contributors <3

## 1.2.0 - 2023-06-29

https://github.com/croustibat/filament-jobs-monitor/releases/tag/1.2.0
Thanks to the contributors <3
## 1.1.0 - 2023-06-13

- Thanks to @cntabana there's now a config file to enable/disable the navigation menu
## 1.0.0 - 2023-05-29

- Initial release : this FilamentPHP plugin contains files to monitor queue jobs. It is compatible with all drivers.
