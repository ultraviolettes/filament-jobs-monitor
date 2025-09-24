# Changelog

All notable changes to `filament-jobs-monitor` will be documented in this file.

## 3.0.0 - 2025-09-24

- Drop support for Filament v3. This package now targets Filament v4 only.
- Require `filament/filament` ^4.0 in composer.
- Migrate Resource form API to `Filament\\Schemas\\Schema` (replaces `Filament\\Forms\\Form`).
- Update table actions to use `Filament\\Actions\\Action` and register via `recordActions()` / `toolbarActions()`.
- Update stats widget to v4 (`getStats()` method name).
- Update `SelectFilter` callback to v4 signature/state handling.
- Fix `FilamentJobsMonitorPlugin::shouldRegisterNavigation()` fallback to respect config.
- Documentation: README updated for v4-only, install command, and note for v3 users pointing to the original library.

### Migration notes

- Resource form signature:
  - From: `public static function form(\\Filament\\Forms\\Form $form): Form { return $form->schema([...]); }`
  - To: `public static function form(\\Filament\\Schemas\\Schema $schema): Schema { return $schema->schema([...]); }`
- Actions: use `Filament\\Actions\\Action` with `recordActions([...])` and `toolbarActions([...])`.
- Modals: prefer `modalContentView('view', fn ($record) => [...])`, fallback to `modalContent(view('view', [...]))` if needed.
- Filters: in v4, the `SelectFilter::query()` closure receives the selected value directly: `->query(fn (Builder $query, $state): Builder => ...)` (or use `->modifyQueryUsing(...)`).
- Widgets: if extending `StatsOverviewWidget`, rename `getCards()` to `getStats()`.

> Filament v3 users: please use the original library we forked from: https://github.com/croustibat/filament-jobs-monitor (v2.*).

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
