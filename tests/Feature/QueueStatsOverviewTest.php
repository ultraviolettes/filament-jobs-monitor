<?php

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\QueueStatsOverview;

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();
});

it('computes execution time stats on the current database driver', function () {
    // Two finished jobs lasting 10s and 20s -> total 30s, average 15s.
    QueueMonitor::create([
        'job_id' => 'uuid-a',
        'name' => 'App\Jobs\FooJob',
        'queue' => 'default',
        'started_at' => '2024-01-01 00:00:00',
        'finished_at' => '2024-01-01 00:00:10',
        'failed' => false,
        'attempt' => 1,
    ]);

    QueueMonitor::create([
        'job_id' => 'uuid-b',
        'name' => 'App\Jobs\FooJob',
        'queue' => 'default',
        'started_at' => '2024-01-01 00:00:00',
        'finished_at' => '2024-01-01 00:00:20',
        'failed' => false,
        'attempt' => 1,
    ]);

    $stats = (new ReflectionMethod(QueueStatsOverview::class, 'getStats'))
        ->invoke(new QueueStatsOverview);

    // The average execution time is computed by the database engine; with the
    // old SQLite-incompatible query this was "0s" (see issue #55).
    expect($stats)->toHaveCount(4)
        ->and($stats[3]->getValue())->toBe('15s');
});
