# Upgrade Guide

## Upgrading from v5.0 to v5.1

Nothing is required: batches and chains are opt-in and the package behaves exactly as in 5.0 until
you turn them on.

To use them, publish and run the new migration, then enable what you need:

```bash
php artisan vendor:publish --tag="filament-jobs-monitor-migrations"
php artisan migrate
```

```php
FilamentJobsMonitorPlugin::make()
    ->enableBatchesPage()      // Batches page, database batch driver only
    ->enableChainsTracking()   // one chain_id per chain; unserializes each dispatched command
```

Laravel only stores batches with the `database` batch driver; on any other driver the Batches page
stays unregistered rather than listing an empty table.

## Upgrading from v4.x to v5.x

v5 drops the compatibility layers v4 carried. Nothing in the plugin's own API changed in this step;
the work is on your side of the dependency constraints.

### Prerequisites

- Filament **5** (v4 is no longer supported — see the [Filament v5 upgrade guide](https://filamentphp.com/docs/5.x/upgrade-guide))
- Laravel **12 or 13**
- PHP **8.3** or newer

If you are still on Filament 4 or Laravel 11, stay on `^4.0`: the `4.x` branch keeps receiving fixes.

### Step 1: Update the dependency

```bash
composer require croustibat/filament-jobs-monitor:^5.0
```

### Step 2: Consider locking the abilities down

Nothing to do here to keep the panel working: viewing, retrying, deleting and clearing the logs
stay open to everyone who can reach the panel, exactly as in v4.

What v5 adds is the ability to check them. Job payloads routinely carry customer data and "Clear
logs" truncates the monitor table, so on a panel with several roles it is worth granting the
abilities explicitly and then closing the door:

```php
FilamentJobsMonitorPlugin::make()
    ->authorize(fn () => auth()->user()?->hasRole('admin'))
    ->authorizeClearLogs(fn () => auth()->user()?->hasRole('super-admin'))
```

```php
// config/filament-jobs-monitor.php
'authorization' => [
    'fallback' => false, // refuse anything the application did not grant
],
```

Policies on the `QueueMonitor` model and plain gates work too. See the Authorization section of the
README for the full list of abilities, their gate names and their policy methods.

### Step 3: Check the sub-navigation position

The Job History / Pending / Failures sub-navigation now defaults to the top of the page instead of
Filament's default side column, because those three pages are wide tables. To keep a side column:

```php
FilamentJobsMonitorPlugin::make()
    ->subNavigationPosition(SubNavigationPosition::Start)
```

### Step 4: Republish the assets

```bash
php artisan filament:assets
```

Nothing else is required: the configuration keys, the plugin API and the database schema are unchanged.

## Upgrading from v2.x to v3.x (Filament v3 to v4)

This guide will help you migrate your application from `filament-jobs-monitor` v2.x (Filament v3) to v3.x (Filament v4).

### Prerequisites

Before upgrading this package, ensure you have:
- Upgraded your application to Filament v4 (see [Filament v4 Upgrade Guide](https://filamentphp.com/docs/4.x/upgrade-guide))
- PHP >= 8.1
- Laravel >= 10.0

### Step 1: Update Composer Dependency

Update your `composer.json` to require version 3.x:

```bash
composer require croustibat/filament-jobs-monitor:^3.0
```

### Step 2: Update Configuration File (Optional)

If you have published the configuration file, you may want to republish it to get the new options:

```bash
php artisan vendor:publish --tag="filament-jobs-monitor-config" --force
```

The new configuration includes a `sub_navigation_position` option:

```php
'resources' => [
    // ... existing config
    'cluster' => null,
    'sub_navigation_position' => null, // SubNavigationPosition::Top or ::Sidebar
],
```

### Step 3: Update Custom Resources (If Extended)

If you have extended the `QueueMonitorResource` class in your application, you'll need to update it to be compatible with Filament v4.

#### Update Form Method

**Before (v2.x):**
```php
use Filament\Forms\Form;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            // Your form fields
        ]);
}
```

**After (v3.x):**
```php
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema
        ->schema([
            // Your form fields
        ]);
}
```

#### Update Action Imports

**Before (v2.x):**
```php
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteBulkAction;
```

**After (v3.x):**
```php
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
```

#### Add HasNavigation Trait

**Before (v2.x):**
```php
class QueueMonitorResource extends Resource
{
    // ...
}
```

**After (v3.x):**
```php
use Filament\Resources\Resource\Concerns\HasNavigation;

class QueueMonitorResource extends Resource
{
    use HasNavigation;

    // ...
}
```

### Step 4: Update Custom Pages (If Extended)

If you have extended any pages like `ListQueueMonitors`, update the action methods:

#### Update Page Actions Method

**Before (v2.x):**
```php
public function getActions(): array
{
    return [
        // Your actions
    ];
}
```

**After (v3.x):**
```php
protected function getHeaderActions(): array
{
    return [
        // Your actions
    ];
}
```

Note the visibility change from `public` to `protected`.

### Step 5: Update Custom Widgets (If Extended)

If you have extended the `QueueStatsOverview` widget:

#### Update Widget Method

**Before (v2.x):**
```php
protected function getCards(): array
{
    return [
        Stat::make('Label', 'value'),
        // ...
    ];
}
```

**After (v3.x):**
```php
protected function getStats(): array
{
    return [
        Stat::make('Label', 'value'),
        // ...
    ];
}
```

### Step 6: Clear Cache

After making all changes, clear your application cache:

```bash
php artisan filament:optimize-clear
php artisan optimize:clear
```

### Breaking Changes Summary

| Component | v2.x (Filament v3) | v3.x (Filament v4) |
|-----------|-------------------|-------------------|
| Form method parameter | `Form $form` | `Schema $schema` |
| Action imports | `Filament\Tables\Actions\*` | `Filament\Actions\*` |
| Page actions method | `public getActions()` | `protected getHeaderActions()` |
| Widget stats method | `getCards()` | `getStats()` |
| Navigation trait | Not required | `use HasNavigation` |
| SubNavigationPosition | `Filament\Pages\SubNavigationPosition` | `Filament\Pages\Enums\SubNavigationPosition` |

### Testing Your Migration

After completing the upgrade:

1. Visit the jobs monitor page in your Filament panel
2. Verify all table columns and filters work correctly
3. Test the job details modal
4. Ensure navigation and badges display properly
5. Check that any custom extensions still function as expected

### Need Help?

If you encounter issues during the upgrade:

- Check the [Filament v4 Upgrade Guide](https://filamentphp.com/docs/4.x/upgrade-guide)
- Review the [package changelog](CHANGELOG.md)
- Open an issue on [GitHub](https://github.com/croustibat/filament-jobs-monitor/issues)

### No Changes Required

If you're using the package with its default configuration and haven't extended any classes, the upgrade should be seamless. Simply update your composer dependency and clear your cache.
