<?php

use Croustibat\FilamentJobsMonitor\Chains;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\QueueMonitorProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

class ChainStepOne implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void {}
}

class ChainStepTwo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public bool $dispatchSomethingElse = false) {}

    public function handle(): void
    {
        if ($this->dispatchSomethingElse) {
            // A job that has nothing to do with the chain, dispatched from
            // inside one of its steps.
            UnrelatedJob::dispatch();
        }
    }
}

class ChainStepThree implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void {}
}

class UnrelatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void {}
}

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();
    $migration = include __DIR__.'/../../database/migrations/add_batches_to_filament-jobs-monitor_table.php.stub';
    $migration->up();

    Schema::create('jobs', function ($table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database', [
        'driver' => 'database',
        'connection' => 'testing',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
    ]);
    config()->set('filament-jobs-monitor.chains.enabled', true);

    Chains::leave();

    // The queue listeners live in a provider the package registers through
    // composer, which testbench does not load for us.
    app()->register(QueueMonitorProvider::class);
});

afterEach(fn () => Chains::leave());

function work(int $jobs): void
{
    for ($i = 0; $i < $jobs; $i++) {
        Artisan::call('queue:work', ['--once' => true, '--no-interaction' => true]);
    }
}

it('gives every step of a chain the same id', function () {
    Bus::chain([new ChainStepOne, new ChainStepTwo, new ChainStepThree])->dispatch();

    work(3);

    $monitors = QueueMonitor::orderBy('started_at')->get();

    expect($monitors)->toHaveCount(3)
        ->and($monitors->pluck('name')->all())->toBe([ChainStepOne::class, ChainStepTwo::class, ChainStepThree::class])
        ->and($monitors->pluck('chain_id')->unique())->toHaveCount(1)
        ->and($monitors->first()->chain_id)->not->toBeNull();
});

it('does not let an unrelated job dispatched from a chained job join the chain', function () {
    Bus::chain([new ChainStepOne, new ChainStepTwo(dispatchSomethingElse: true), new ChainStepThree])->dispatch();

    work(4);

    $chainIds = QueueMonitor::pluck('chain_id', 'name');

    expect($chainIds)->toHaveCount(4)
        ->and($chainIds[ChainStepOne::class])->not->toBeNull()
        ->and($chainIds[UnrelatedJob::class])->toBeNull()
        ->and($chainIds[ChainStepThree::class])->toBe($chainIds[ChainStepOne::class]);
});

it('leaves a job dispatched on its own without a chain id', function () {
    UnrelatedJob::dispatch();

    work(1);

    expect(QueueMonitor::first()->chain_id)->toBeNull();
});

it('tracks nothing while the feature is off', function () {
    config()->set('filament-jobs-monitor.chains.enabled', false);

    Bus::chain([new ChainStepOne, new ChainStepTwo])->dispatch();

    work(2);

    expect(QueueMonitor::pluck('chain_id')->filter())->toBeEmpty();
});

it('groups the steps through the chain() helper', function () {
    Bus::chain([new ChainStepOne, new ChainStepTwo, new ChainStepThree])->dispatch();

    work(3);

    $second = QueueMonitor::where('name', ChainStepTwo::class)->first();

    expect($second->chain()->pluck('name')->all())
        ->toBe([ChainStepOne::class, ChainStepTwo::class, ChainStepThree::class]);
});
