# Upgrade Guide

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
