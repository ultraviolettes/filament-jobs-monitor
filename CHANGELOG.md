# Changelog

All notable changes to `filament-jobs-monitor` will be documented in this file.

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
