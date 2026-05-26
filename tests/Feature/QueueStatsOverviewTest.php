<?php

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\QueueStatsOverview;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();
});

it('computes elapsed time correctly via driver-specific epoch seconds', function () {
    QueueMonitor::create([
        'job_id' => '1',
        'name' => 'TestJob',
        'queue' => 'default',
        'started_at' => '2026-01-01 10:00:00',
        'finished_at' => '2026-01-01 10:00:30',
        'failed' => false,
        'attempt' => 1,
    ]);

    QueueMonitor::create([
        'job_id' => '2',
        'name' => 'TestJob',
        'queue' => 'default',
        'started_at' => '2026-01-01 10:00:00',
        'finished_at' => '2026-01-01 10:01:30',
        'failed' => false,
        'attempt' => 1,
    ]);

    $widget = new QueueStatsOverview;
    $driver = DB::connection()->getConfig('driver');

    $build = (new ReflectionMethod($widget, 'buildAggregateMode'));
    $build->setAccessible(true);

    $avg = QueueMonitor::query()
        ->selectRaw($build->invoke($widget, 'AVG', 'finished_at', 'started_at', $driver).' as average_time_elapsed')
        ->value('average_time_elapsed');

    $sum = QueueMonitor::query()
        ->selectRaw($build->invoke($widget, 'SUM', 'finished_at', 'started_at', $driver).' as total_time_elapsed')
        ->value('total_time_elapsed');

    // 30s and 90s durations
    expect((float) $avg)->toBe(60.0)
        ->and((float) $sum)->toBe(120.0);
});
