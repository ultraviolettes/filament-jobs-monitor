<?php

use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // The FailedJob model follows the queue config, which points at the default
    // connection; the suite runs on the in-memory `testing` one.
    config()->set('queue.failed.database', 'testing');

    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();

    $migration = include __DIR__.'/../../database/migrations/add_failures_to_filament-jobs-monitor_table.php.stub';
    $migration->up();

    Schema::create('failed_jobs', function ($table) {
        $table->id();
        $table->string('uuid')->unique();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
    });
});

function monitor(array $attributes = []): QueueMonitor
{
    return QueueMonitor::create(array_merge([
        'job_id' => 'job-1',
        'name' => 'App\\Jobs\\ImportOrders',
        'queue' => 'imports',
        'started_at' => now()->subMinutes(3),
        'finished_at' => now()->subMinutes(1),
        'failed' => false,
        'attempt' => 1,
        'progress' => 100,
    ], $attributes));
}

function renderDetails(QueueMonitor $record): string
{
    return view('filament-jobs-monitor::queue-monitor-details', [
        'record' => $record,
        'payload' => QueueMonitorResource::payloadFor($record),
        'failuresUrl' => QueueMonitorResource::failuresUrlFor($record),
    ])->render();
}

it('shows the metadata of a succeeded job instead of three empty values', function () {
    $html = renderDetails(monitor());

    expect($html)
        ->toContain('job-1')
        ->toContain('imports')
        ->toContain('App\\Jobs\\ImportOrders')
        ->toContain('100%')
        ->toContain(__('filament-jobs-monitor::translations.duration'))
        ->toContain(__('filament-jobs-monitor::translations.succeeded'))
        // nothing failed, so no stack trace block
        ->not->toContain(__('filament-jobs-monitor::translations.stack_trace'));
});

it('tells the payload is gone rather than showing an empty block', function () {
    expect(renderDetails(monitor()))->toContain(__('filament-jobs-monitor::translations.no_payload'));
});

it('shows the payload and the stack trace of a failed job', function () {
    $record = monitor([
        'failed' => true,
        'exception_class' => RuntimeException::class,
        'exception_message' => 'Connection refused',
        'exception' => "RuntimeException: Connection refused in /app/app/Jobs/ImportOrders.php:42\nStack trace:\n#0 /app/vendor/laravel/framework/Queue/Jobs/Job.php(98): App\\Jobs\\ImportOrders->handle()\n#1 {main}",
    ]);

    FailedJob::create([
        'uuid' => $record->job_id,
        'connection' => 'database',
        'queue' => 'imports',
        // the model casts `payload` to an array, Laravel writes the JSON itself
        'payload' => ['displayName' => 'App\\Jobs\\ImportOrders', 'data' => ['orderId' => 4821]],
        'exception' => 'RuntimeException: Connection refused',
    ]);

    $html = renderDetails($record);

    expect($html)
        ->toContain(__('filament-jobs-monitor::translations.stack_trace'))
        ->toContain('ImportOrders.php')
        ->toContain('orderId')
        ->toContain('4821')
        ->toContain(__('filament-jobs-monitor::translations.failed'))
        ->not->toContain(__('filament-jobs-monitor::translations.no_payload'));
});

it('keeps the exception message visible when no stack trace was stored', function () {
    $record = monitor([
        'failed' => true,
        'exception_message' => 'Connection refused',
        'exception' => null,
    ]);

    expect(renderDetails($record))->toContain('Connection refused');
});

it('does not link to the failure group when the job is not grouped', function () {
    expect(QueueMonitorResource::failuresUrlFor(monitor()))->toBeNull()
        ->and(renderDetails(monitor()))->not->toContain(__('filament-jobs-monitor::translations.view_failure_group'));
});

it('reads no payload for a job that is neither failed nor queued', function () {
    expect(QueueMonitorResource::payloadFor(monitor()))->toBeNull();
});
