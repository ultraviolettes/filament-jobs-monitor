# Background Jobs monitoring like Horizon for all drivers for FilamentPHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/fatrex/filament-jobs-monitor.svg?style=flat-square)](https://packagist.org/packages/fatrex/filament-jobs-monitor)
[![Total Downloads](https://img.shields.io/packagist/dt/fatrex/filament-jobs-monitor.svg?style=flat-square)](https://packagist.org/packages/fatrex/filament-jobs-monitor)

This is a package to monitor background jobs for FilamentPHP (v4). It is inspired by Laravel Horizon and is compatible with all drivers.

<img width="800" alt="Screenshot 2023-09-13 at 23 18 44" src="https://github.com/croustibat/filament-jobs-monitor/assets/1169456/a5d5a088-865f-4265-bd37-13dce213f529">
<img width="800" alt="Screenshot 2023-09-13 at 23 18 23" src="https://github.com/croustibat/filament-jobs-monitor/assets/1169456/e0a7730e-f459-4d99-8a40-5f35337385eb">

## Installation

Check your FilamentPHP version before installing:

| Package | FilamentPHP | PHP    |
| ------- | ----------- |--------|
| 3.*     | 4.*         | >= 8.1 |

Install the package via composer:

```bash
composer require fatrex/filament-jobs-monitor
```

Publish and run the migrations using:

```bash
php artisan vendor:publish --tag="filament-jobs-monitor-migrations"
php artisan migrate
```

## Usage

### Configuration

The global plugin config can be published using the command below:

```bash
php artisan vendor:publish --tag="filament-jobs-monitor-config"
```

This is the content of the published config file:

```php
return [
    'resources' => [
        'enabled' => true,
        'label' => 'Job',
        'plural_label' => 'Jobs',
        'navigation_group' => 'Settings',
        'navigation_icon' => 'heroicon-o-cpu-chip',
        'navigation_sort' => null,
        'navigation_count_badge' => false,
        'resource' => Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource::class,
    ],
    'pruning' => [
        'enabled' => true,
        'retention_days' => 7,
    ],
    'queues' => [
        'default'
    ],
];
```

**NOTE:** Since there isn't a universal way to retrieve all used queues, it's necessary to define them to obtain all pending jobs. 

### Extending Model

Sometimes it's useful to extend the model to add some custom methods. You can do it by extending the model by creating your own model :

```php 
$ php artisan make:model MyQueueMonitor
```

Then you can extend the model by adding your own methods :

```php

    <?php

    namespace App\Models;

    use \Croustibat\FilamentJobsMonitor\Models\QueueMonitor as CroustibatQueueMonitor;

    class MyQueueMonitor extends CroustibatQueueMonitor {}

```

### Using Filament Panels

If you are using Filament Panels, you can register the Plugin to your Panel configuration. This will register the plugin's resources as well as allow you to set configuration using optional chainable methods.

For example in your `app/Providers/Filament/AdminPanelProvider.php` file:

```php
<?php


use \Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;

...

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentJobsMonitorPlugin::make()
        ]);
}
```

## Usage

Just run a Background Job and go to the route `/admin/queue-monitors` to see the jobs.

## Example

Go to [example](./examples/) folder to see a Job example file.

Then you can call your Job with the following code:

```php
    public static function table(Table $table): Table
    {
        return $table

        // rest of your code
        ...

        ->bulkActions([
            BulkAction::make('export-jobs')
            ->label('Background Export')
            ->icon('heroicon-o-cog')
            ->action(function (Collection $records) {
                UsersCsvExportJob::dispatch($records, 'users.csv');
                Notification::make()
                    ->title('Export is ready')
                    ->body('Your export is ready. You can download it from the exports page.')
                    ->success()
                    ->seconds(5)
                    ->icon('heroicon-o-inbox-in')
                    ->send();
            })
        ])
    }
```

### Enabling navigation


````php
        // AdminPanelProvider.php
        ->plugins([
            // ...
            FilamentJobsMonitorPlugin::make()
                ->enableNavigation(),
        ])
```

Or you can use a closure to enable navigation only for specific users:

```php

        // AdminPanelProvider.php
        ->plugins([
            // ...
            FilamentJobsMonitorPlugin::make()
                ->enableNavigation(
                    fn () => auth()->user()->can('view_queue_job') || auth()->user()->can('view_any_queue_job)'),
                ),
        ])
```

> Note for Filament v3 users
>
> This package now targets Filament v4 only. If you need Filament v3 support, please use the original library we forked from: [croustibat/filament-jobs-monitor](https://github.com/croustibat/filament-jobs-monitor) (v2.*).
