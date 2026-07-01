<?php

use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    // Load the package migration into the in-memory sqlite database.
    if (! Schema::hasTable('queue_monitors')) {
        $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
        $migration->up();
    }

    config()->set('filament-jobs-monitor.pruning.enabled', true);
    config()->set('filament-jobs-monitor.pruning.retention_days', 7);

    // Reset any plugin overrides between tests.
    app()->forgetInstance(FilamentJobsMonitorPlugin::class);

    QueueMonitor::query()->delete();
});

function createMonitor(int $daysOld): QueueMonitor
{
    $monitor = QueueMonitor::create([
        'job_id' => (string) Str::uuid(),
        'name' => 'TestJob',
        'queue' => 'default',
        'failed' => false,
        'attempt' => 1,
    ]);

    $createdAt = now()->subDays($daysOld);
    $monitor->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->saveQuietly();

    return $monitor;
}

it('prunes records older than the retention period', function () {
    createMonitor(10); // older than 7 days -> should be pruned
    createMonitor(3);  // newer than 7 days -> should be kept

    expect(QueueMonitor::count())->toBe(2);

    $pruned = (new QueueMonitor)->pruneAll();

    expect($pruned)->toBe(1)
        ->and(QueueMonitor::count())->toBe(1);
});

it('does not prune anything when pruning is disabled', function () {
    config()->set('filament-jobs-monitor.pruning.enabled', false);
    app()->forgetInstance(FilamentJobsMonitorPlugin::class);

    createMonitor(30);

    $pruned = (new QueueMonitor)->pruneAll();

    expect($pruned)->toBe(0)
        ->and(QueueMonitor::count())->toBe(1);
});

it('prunes via the dedicated artisan command', function () {
    createMonitor(10);
    createMonitor(2);

    $this->artisan('filament-jobs-monitor:prune')
        ->assertSuccessful();

    expect(QueueMonitor::count())->toBe(1);
});
