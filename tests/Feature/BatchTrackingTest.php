<?php

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\QueueMonitorProvider;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Support\Facades\Schema;

function runMigrations(bool $withBatches = true): void
{
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();

    if ($withBatches) {
        $migration = include __DIR__.'/../../database/migrations/add_batches_to_filament-jobs-monitor_table.php.stub';
        $migration->up();
    }
}

/**
 * A job as the worker sees it, with the payload Laravel builds.
 *
 * @param  array<string, mixed>  $data
 */
function fakeJob(array $data = [], string $id = 'job-1'): JobContract
{
    $job = Mockery::mock(JobContract::class);
    $job->shouldReceive('payload')->andReturn(['uuid' => $id, 'data' => $data]);
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getRawBody')->andReturn('{}');
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\Example');

    return $job;
}

function startJob(JobContract $job): void
{
    (new ReflectionMethod(QueueMonitorProvider::class, 'jobStarted'))->invoke(null, $job);
}

function forgetBatchSupport(): void
{
    $property = new ReflectionProperty(QueueMonitorProvider::class, 'supportsBatchTracking');
    $property->setValue(null, null);
}

beforeEach(function () {
    forgetBatchSupport();
});

afterEach(function () {
    forgetBatchSupport();
});

it('records the batch a job was dispatched in', function () {
    runMigrations();

    startJob(fakeJob(['batchId' => 'b-123']));

    expect(QueueMonitor::first()->batch_id)->toBe('b-123');
});

it('leaves both columns null for a plain job', function () {
    runMigrations();

    startJob(fakeJob());

    expect(QueueMonitor::first())
        ->batch_id->toBeNull()
        ->chain_id->toBeNull();
});

it('keeps working when the batch migration has not been run', function () {
    runMigrations(withBatches: false);

    expect(Schema::hasColumn('queue_monitors', 'batch_id'))->toBeFalse();

    startJob(fakeJob(['batchId' => 'b-123']));

    // The monitor is still created, simply without the batch id.
    expect(QueueMonitor::count())->toBe(1)
        ->and(QueueMonitor::first()->name)->toBe('App\\Jobs\\Example');
});

it('ignores a batch id that is not a string', function () {
    runMigrations();

    startJob(fakeJob(['batchId' => ['nope']]));

    expect(QueueMonitor::first()->batch_id)->toBeNull();
});

it('returns no batch for a monitor that belongs to none', function () {
    runMigrations();

    $monitor = QueueMonitor::create([
        'job_id' => 'x', 'queue' => 'default', 'started_at' => now(), 'failed' => false, 'attempt' => 1,
    ]);

    expect($monitor->batch())->toBeNull()
        ->and($monitor->chain())->toBeEmpty();
});

it('lists the monitored steps of a chain in run order', function () {
    runMigrations();

    foreach ([['c-1', 'step-3', 3], ['c-1', 'step-1', 1], ['c-1', 'step-2', 2], ['c-2', 'other', 1]] as [$chain, $jobId, $minutes]) {
        QueueMonitor::create([
            'job_id' => $jobId,
            'chain_id' => $chain,
            'queue' => 'default',
            'started_at' => now()->addMinutes($minutes),
            'failed' => false,
            'attempt' => 1,
        ]);
    }

    $chain = QueueMonitor::where('job_id', 'step-2')->first()->chain();

    expect($chain->pluck('job_id')->all())->toBe(['step-1', 'step-2', 'step-3']);
});
