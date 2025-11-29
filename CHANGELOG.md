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

## Previous releases

For releases prior to 3.0.0, see the original project’s changelog:
https://github.com/croustibat/filament-jobs-monitor/blob/main/CHANGELOG.md