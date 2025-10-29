# Changelog

All notable changes to `filament-jobs-monitor` will be documented in this file.

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
