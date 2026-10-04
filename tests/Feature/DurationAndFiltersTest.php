<?php

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Illuminate\Support\Str;

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();
});

function ranFor(int $seconds, string $queue = 'default', string $name = 'App\\Jobs\\Example'): QueueMonitor
{
    return QueueMonitor::create([
        'job_id' => Str::uuid()->toString(),
        'name' => $name,
        'queue' => $queue,
        'started_at' => now()->subMinutes(10),
        'finished_at' => now()->subMinutes(10)->addSeconds($seconds),
        'failed' => false,
        'attempt' => 1,
    ]);
}

it('sorts by duration in SQL rather than by finished_at', function () {
    ranFor(30, name: 'medium');
    ranFor(120, name: 'slow');
    ranFor(2, name: 'fast');

    $slowest = QueueMonitor::query()
        ->orderByRaw(QueueMonitor::elapsedSeconds().' desc')
        ->pluck('name')
        ->all();

    expect($slowest)->toBe(['slow', 'medium', 'fast']);
});

it('computes a duration only once a job has finished', function () {
    $finished = ranFor(90);
    $running = QueueMonitor::create([
        'job_id' => 'running',
        'queue' => 'default',
        'started_at' => now()->subMinute(),
        'failed' => false,
        'attempt' => 1,
    ]);

    expect(QueueMonitorResource::durationFor($finished))->toContain('1m')
        ->and(QueueMonitorResource::durationFor($running))->toBeNull();
});

it('offers the queues that actually appear in the table', function () {
    ranFor(1, queue: 'imports');
    ranFor(1, queue: 'default');
    ranFor(1, queue: 'imports');

    expect(QueueMonitor::distinctQueues())->toBe([
        'default' => 'default',
        'imports' => 'imports',
    ]);
});

it('ignores monitors without a queue', function () {
    ranFor(1, queue: 'imports');
    QueueMonitor::create(['job_id' => 'no-queue', 'queue' => null, 'started_at' => now(), 'failed' => false, 'attempt' => 1]);

    expect(QueueMonitor::distinctQueues())->toBe(['imports' => 'imports']);
});

it('builds the epoch expression of the current driver', function () {
    // The suite runs on SQLite, where a bare datetime subtraction returns 0 (#55).
    expect(QueueMonitor::epochSeconds('started_at'))->toContain('strftime')
        ->and(QueueMonitor::elapsedSeconds())->toContain('finished_at')
        ->and(QueueMonitor::elapsedSeconds())->toContain('started_at');
});
