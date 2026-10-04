<?php

use Croustibat\FilamentJobsMonitor\Models\JobBatch;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListBatches;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function createBatchesTable(): void
{
    Schema::create('job_batches', function ($table) {
        $table->string('id')->primary();
        $table->string('name');
        $table->integer('total_jobs');
        $table->integer('pending_jobs');
        $table->integer('failed_jobs');
        $table->longText('failed_job_ids');
        $table->mediumText('options')->nullable();
        $table->integer('cancelled_at')->nullable();
        $table->integer('created_at');
        $table->integer('finished_at')->nullable();
    });
}

function batch(array $attributes = []): JobBatch
{
    return JobBatch::create(array_merge([
        'id' => (string) Str::uuid(),
        'name' => 'Import orders',
        'total_jobs' => 10,
        'pending_jobs' => 10,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'created_at' => now()->subMinutes(5)->getTimestamp(),
        'finished_at' => null,
    ], $attributes));
}

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();
    $migration = include __DIR__.'/../../database/migrations/add_batches_to_filament-jobs-monitor_table.php.stub';
    $migration->up();

    config()->set('queue.batching.database', 'testing');
    config()->set('filament-jobs-monitor.batches.enabled', true);
    config()->set('filament-jobs-monitor.authorization.fallback', true);
});

it('stays hidden when the job_batches table is absent', function () {
    expect(JobBatch::isSupported())->toBeFalse()
        ->and(ListBatches::isEnabled())->toBeFalse()
        ->and(ListBatches::canAccess())->toBeFalse()
        ->and(QueueMonitorResource::subNavigationPages())->not->toContain(ListBatches::class);
});

it('stays hidden while the page is not enabled', function () {
    createBatchesTable();
    config()->set('filament-jobs-monitor.batches.enabled', false);

    expect(JobBatch::isSupported())->toBeTrue()
        ->and(ListBatches::isEnabled())->toBeFalse();
});

it('stays hidden when batches are not stored in the database', function () {
    createBatchesTable();
    config()->set('queue.batching.driver', 'dynamodb');

    expect(JobBatch::isSupported())->toBeFalse();
});

it('joins the sub-navigation once enabled and supported', function () {
    createBatchesTable();

    expect(ListBatches::isEnabled())->toBeTrue()
        ->and(QueueMonitorResource::subNavigationPages())->toContain(ListBatches::class);
});

it('reports progress from the batch itself', function () {
    createBatchesTable();

    $batch = batch(['total_jobs' => 10, 'pending_jobs' => 4]);

    expect($batch->processedJobs())->toBe(6)
        ->and($batch->progress())->toBe(60)
        ->and($batch->status())->toBe('processing');
});

it('maps every batch state', function (array $attributes, string $expected) {
    createBatchesTable();

    expect(batch($attributes)->status())->toBe($expected);
})->with([
    'pending' => [['pending_jobs' => 10], 'pending'],
    'processing' => [['pending_jobs' => 3], 'processing'],
    'finished' => [['pending_jobs' => 0, 'finished_at' => 1759500000], 'finished'],
    'failed' => [['pending_jobs' => 0, 'failed_jobs' => 2, 'finished_at' => 1759500000], 'failed'],
    'cancelled' => [['pending_jobs' => 5, 'cancelled_at' => 1759500000], 'cancelled'],
]);

it('names an unnamed batch after its id', function () {
    createBatchesTable();

    $named = batch(['name' => 'Nightly export']);
    $unnamed = batch(['name' => '', 'id' => 'abc12345-ffff-0000-1111-222222222222']);

    expect($named->displayName())->toBe('Nightly export')
        ->and($unnamed->displayName())->toBe(__('filament-jobs-monitor::translations.unnamed_batch', ['id' => 'abc12345']));
});

it('lists the monitored jobs of a batch, in run order', function () {
    createBatchesTable();

    $batch = batch(['id' => 'batch-1']);

    foreach ([['second', 2], ['first', 1], ['elsewhere', 3]] as [$jobId, $minutes]) {
        QueueMonitor::create([
            'job_id' => $jobId,
            'name' => 'App\\Jobs\\Example',
            'batch_id' => $jobId === 'elsewhere' ? 'batch-2' : 'batch-1',
            'queue' => 'default',
            'started_at' => now()->addMinutes($minutes),
            'failed' => false,
            'attempt' => 1,
        ]);
    }

    expect($batch->monitors()->pluck('job_id')->all())->toBe(['first', 'second']);
});

it('offers the batches jobs were monitored in as a filter', function () {
    createBatchesTable();

    QueueMonitor::create([
        'job_id' => 'j1', 'batch_id' => 'abcdef123456', 'queue' => 'default',
        'started_at' => now(), 'failed' => false, 'attempt' => 1,
    ]);

    expect(QueueMonitorResource::tracksBatches())->toBeTrue()
        ->and(QueueMonitorResource::batchFilterOptions())->toBe(['abcdef123456' => 'abcdef12']);
});

it('scopes batches to the tenant that ran their jobs', function () {
    createBatchesTable();

    batch(['id' => 'batch-tenant-1']);
    batch(['id' => 'batch-tenant-2']);

    foreach ([['batch-tenant-1', 't1'], ['batch-tenant-2', 't2']] as [$batchId, $tenant]) {
        QueueMonitor::create([
            'job_id' => $batchId, 'batch_id' => $batchId, 'tenant_id' => $tenant,
            'queue' => 'default', 'started_at' => now(), 'failed' => false, 'attempt' => 1,
        ]);
    }

    expect(JobBatch::query()->forTenant('t1')->pluck('id')->all())->toBe(['batch-tenant-1']);
});
