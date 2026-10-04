<?php

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\QueueDiscovery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();

    QueueDiscovery::flush();
    config()->set('filament-jobs-monitor.queues', null);
    config()->set('filament-jobs-monitor.queue_discovery.cache_ttl', 0);
    config()->set('queue.connections', []);
    config()->set('horizon.defaults', []);
});

function monitorOn(string $queue): void
{
    QueueMonitor::create([
        'job_id' => Str::uuid()->toString(),
        'queue' => $queue,
        'started_at' => now(),
        'failed' => false,
        'attempt' => 1,
    ]);
}

it('discovers the queues jobs actually ran on', function () {
    monitorOn('imports');
    monitorOn('emails');
    monitorOn('imports');

    expect(QueueDiscovery::all())->toBe(['emails', 'imports']);
});

it('adds the default queue of each configured connection', function () {
    monitorOn('imports');
    config()->set('queue.connections', [
        'redis' => ['driver' => 'redis', 'queue' => 'high'],
        'sqs' => ['driver' => 'sqs', 'queue' => 'low'],
        'sync' => ['driver' => 'sync'],
    ]);

    expect(QueueDiscovery::all())->toBe(['high', 'imports', 'low']);
});

it('adds the queues Horizon supervises', function () {
    config()->set('horizon.defaults', [
        'supervisor-1' => ['queue' => ['default', 'notifications']],
    ]);

    expect(QueueDiscovery::all())->toBe(['default', 'notifications']);
});

it('reads the failed_jobs table when it exists', function () {
    Schema::create('failed_jobs', function ($table) {
        $table->id();
        $table->string('uuid')->unique();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
    });

    config()->set('queue.failed.database', 'testing');

    DB::connection('testing')->table('failed_jobs')->insert([
        'uuid' => 'a', 'connection' => 'database', 'queue' => 'exports',
        'payload' => '{}', 'exception' => 'x', 'failed_at' => now(),
    ]);

    expect(QueueDiscovery::all())->toContain('exports');
});

it('survives a table that does not exist', function () {
    // No failed_jobs table here, and the jobs table only exists on the database driver.
    config()->set('queue.default', 'database');
    monitorOn('imports');

    expect(QueueDiscovery::all())->toBe(['imports']);
});

it('falls back to the default queue when nothing can be discovered', function () {
    expect(QueueDiscovery::all())->toBe(['default']);
});

it('skips discovery entirely when queues are configured explicitly', function () {
    monitorOn('imports');
    config()->set('filament-jobs-monitor.queues', ['high', 'low', 'high']);

    expect(QueueDiscovery::all())->toBe(['high', 'low']);
});

it('caches the discovered list', function () {
    config()->set('filament-jobs-monitor.queue_discovery.cache_ttl', 60);
    monitorOn('imports');

    expect(QueueDiscovery::all())->toBe(['imports']);

    monitorOn('emails');

    expect(QueueDiscovery::all())->toBe(['imports'])
        ->and(tap(QueueDiscovery::all(), fn () => QueueDiscovery::flush()))->toBe(['imports']);

    expect(QueueDiscovery::all())->toBe(['emails', 'imports']);
});
