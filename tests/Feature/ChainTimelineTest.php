<?php

use Croustibat\FilamentJobsMonitor\Chains;
use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();
    $migration = include __DIR__.'/../../database/migrations/add_failures_to_filament-jobs-monitor_table.php.stub';
    $migration->up();
    $migration = include __DIR__.'/../../database/migrations/add_batches_to_filament-jobs-monitor_table.php.stub';
    $migration->up();

    config()->set('queue.failed.database', 'testing');

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

/**
 * Stands in for the job of a broken chain: what matters is the `chained`
 * property its serialized form carries.
 */
class FakeChainCommand
{
    /** @var array<int, string> */
    public array $chained = [];
}

function step(string $name, int $minute, array $attributes = []): QueueMonitor
{
    return QueueMonitor::create(array_merge([
        'job_id' => $name,
        'name' => $name,
        'chain_id' => 'chain-1',
        'queue' => 'default',
        'started_at' => now()->addMinutes($minute),
        'finished_at' => now()->addMinutes($minute)->addSecond(),
        'failed' => false,
        'attempt' => 1,
    ], $attributes));
}

/**
 * A failed job whose serialized command still carries the remaining steps,
 * which is how a broken chain says what it never reached.
 */
function failedWithChained(QueueMonitor $monitor, array $remaining): void
{
    $chained = array_map(fn (string $class): string => 'O:'.strlen($class).':"'.$class.'":0:{}', $remaining);

    $instance = new FakeChainCommand;
    $instance->chained = $chained;
    $command = serialize($instance);

    FailedJob::create([
        'uuid' => $monitor->job_id,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => ['data' => ['command' => $command]],
        'exception' => 'x',
    ]);
}

function renderTimeline(QueueMonitor $current): string
{
    return view('filament-jobs-monitor::partials.chain-timeline', [
        'timeline' => Chains::timelineFor($current),
        'current' => $current,
        'chainUrl' => null,
    ])->render();
}

it('has no timeline for a job outside any chain', function () {
    $alone = step('App\\Jobs\\Alone', 1, ['chain_id' => null]);

    expect(Chains::timelineFor($alone))
        ->total->toBe(0)
        ->steps->toBeEmpty()
        ->neverReached->toBeEmpty();
});

it('orders the steps of a chain by their start', function () {
    step('App\\Jobs\\Third', 3);
    step('App\\Jobs\\First', 1);
    $second = step('App\\Jobs\\Second', 2);

    $timeline = Chains::timelineFor($second);

    expect($timeline['total'])->toBe(3)
        ->and($timeline['truncated'])->toBeFalse()
        ->and($timeline['steps']->pluck('name')->all())
        ->toBe(['App\\Jobs\\First', 'App\\Jobs\\Second', 'App\\Jobs\\Third']);
});

it('truncates a long chain', function () {
    foreach (range(1, 12) as $i) {
        step("App\\Jobs\\Step{$i}", $i);
    }

    $timeline = Chains::timelineFor(QueueMonitor::first(), limit: 5);

    expect($timeline['total'])->toBe(12)
        ->and($timeline['steps'])->toHaveCount(5)
        ->and($timeline['truncated'])->toBeTrue();
});

it('names the steps a broken chain never reached', function () {
    step('App\\Jobs\\First', 1);
    $failed = step('App\\Jobs\\Second', 2, [
        'failed' => true,
        'exception_message' => 'Connection refused',
    ]);

    failedWithChained($failed, ['App\\Jobs\\Third', 'App\\Jobs\\Fourth']);

    expect(Chains::timelineFor($failed)['neverReached'])
        ->toBe(['App\\Jobs\\Third', 'App\\Jobs\\Fourth']);
});

it('renders a broken chain as succeeded, failed, never reached', function () {
    step('App\\Jobs\\First', 1);
    $failed = step('App\\Jobs\\Second', 2, [
        'failed' => true,
        'exception_message' => 'Connection refused',
    ]);
    failedWithChained($failed, ['App\\Jobs\\Third']);

    $html = renderTimeline($failed);

    expect($html)
        ->toContain('✓')
        ->toContain('✕')
        ->toContain('○')
        ->toContain('App\\Jobs\\Third')
        ->toContain(__('filament-jobs-monitor::translations.never_reached'))
        // the break point carries its message inline
        ->toContain('Connection refused')
        ->toContain(__('filament-jobs-monitor::translations.this_job'));
});

it('reads the whole chain in a single query', function () {
    foreach (range(1, 6) as $i) {
        step("App\\Jobs\\Step{$i}", $i);
    }

    $current = QueueMonitor::first();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    Chains::timelineFor($current);

    // one for the steps; a successful last step needs no failed_jobs lookup
    expect($queries)->toBe(1);
});
