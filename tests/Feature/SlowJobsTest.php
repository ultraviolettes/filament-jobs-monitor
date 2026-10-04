<?php

use Croustibat\FilamentJobsMonitor\Events\JobMonitorSlow;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\QueueMonitorProvider;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\SlowJobs;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();

    SlowJobs::flush();
    config()->set('filament-jobs-monitor.slow.cache_ttl', 0);
    config()->set('filament-jobs-monitor.slow.min_samples', 3);
});

function ran(string $name, int $seconds, int $daysAgo = 0): QueueMonitor
{
    $startedAt = now()->subDays($daysAgo)->subHour();

    return QueueMonitor::create([
        'job_id' => Str::uuid()->toString(),
        'name' => $name,
        'queue' => 'default',
        'started_at' => $startedAt,
        'finished_at' => $startedAt->copy()->addSeconds($seconds),
        'failed' => false,
        'attempt' => 1,
    ]);
}

it('computes a median and a p95 per job class', function () {
    foreach ([1, 2, 3, 4, 100] as $seconds) {
        ran('App\\Jobs\\Fast', $seconds);
    }

    $baseline = SlowJobs::baselines()['App\\Jobs\\Fast'];

    expect($baseline['runs'])->toBe(5)
        ->and($baseline['median'])->toBe(3.0)
        ->and($baseline['p95'])->toBe(100.0);
});

it('flags a run that is far slower than its own class median', function () {
    foreach ([2, 2, 3, 2, 3] as $seconds) {
        ran('App\\Jobs\\Import', $seconds);
    }

    $slow = ran('App\\Jobs\\Import', 20);
    SlowJobs::flush();

    $inspection = SlowJobs::inspect($slow);

    expect($inspection['slow'])->toBeTrue()
        ->and($inspection['reason'])->toBe('anomaly')
        ->and($inspection['ratio'])->toBeGreaterThan(5.0);
});

it('does not flag a class that has not run often enough', function () {
    config()->set('filament-jobs-monitor.slow.min_samples', 20);

    foreach ([2, 2, 3] as $seconds) {
        ran('App\\Jobs\\Cold', $seconds);
    }

    $slow = ran('App\\Jobs\\Cold', 20);
    SlowJobs::flush();

    expect(SlowJobs::inspect($slow)['slow'])->toBeFalse();
});

it('flags a run over the absolute threshold whatever its class median', function () {
    config()->set('filament-jobs-monitor.slow.threshold_seconds', 60);

    $slow = ran('App\\Jobs\\Report', 120);

    expect(SlowJobs::inspect($slow))
        ->slow->toBeTrue()
        ->reason->toBe('threshold');
});

it('never flags anything when detection is disabled', function () {
    config()->set('filament-jobs-monitor.slow.enabled', false);

    expect(SlowJobs::isSlow(ran('App\\Jobs\\Report', 600)))->toBeFalse();
});

it('leaves a running job alone', function () {
    $running = QueueMonitor::create([
        'job_id' => 'running',
        'name' => 'App\\Jobs\\Report',
        'started_at' => now()->subHours(3),
        'failed' => false,
        'attempt' => 1,
    ]);

    expect(SlowJobs::isSlow($running))->toBeFalse();
});

it('ranks the slowest classes and compares them to the previous window', function () {
    foreach ([10, 12, 11] as $seconds) {
        ran('App\\Jobs\\Slow', $seconds);
    }
    foreach ([1, 1, 2] as $seconds) {
        ran('App\\Jobs\\Quick', $seconds);
    }
    // the same class, twice as fast in the previous window
    foreach ([5, 6, 5] as $seconds) {
        ran('App\\Jobs\\Slow', $seconds, daysAgo: 9);
    }

    $slowest = SlowJobs::slowest();

    expect($slowest[0]['name'])->toBe('App\\Jobs\\Slow')
        ->and($slowest[0]['median'])->toBe(11.0)
        ->and($slowest[0]['previous_median'])->toBe(5.0)
        ->and($slowest[0]['trend'])->toBe(1.2)
        ->and($slowest[1]['name'])->toBe('App\\Jobs\\Quick');
});

it('reads the baselines in a single query', function () {
    foreach (['A', 'B', 'C', 'D'] as $class) {
        foreach ([1, 2, 3] as $seconds) {
            ran("App\\Jobs\\{$class}", $seconds);
        }
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    SlowJobs::baselines();

    expect($queries)->toBe(1);
});

it('dispatches JobMonitorSlow when a job finishes slowly', function () {
    Event::fake([JobMonitorSlow::class]);
    config()->set('filament-jobs-monitor.slow.threshold_seconds', 10);

    $job = Mockery::mock(JobContract::class);
    $job->shouldReceive('payload')->andReturn(['uuid' => 'slow-job', 'displayName' => 'App\\Jobs\\Report']);
    $job->shouldReceive('attempts')->andReturn(1);
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('getRawBody')->andReturn('{}');
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\Report');

    QueueMonitor::create([
        'job_id' => 'slow-job',
        'name' => 'App\\Jobs\\Report',
        'queue' => 'default',
        'started_at' => now()->subMinutes(5),
        'failed' => false,
        'attempt' => 1,
    ]);

    // jobFinished() is protected and the provider's constructor wants the app,
    // so go through reflection rather than instantiating a service provider.
    (new ReflectionMethod(QueueMonitorProvider::class, 'jobFinished'))->invoke(null, $job);

    Event::assertDispatched(JobMonitorSlow::class, function (JobMonitorSlow $event): bool {
        return $event->reason === 'threshold' && $event->duration >= 300;
    });
});

it('tells why a run is flagged', function () {
    config()->set('filament-jobs-monitor.slow.threshold_seconds', 60);

    expect(QueueMonitorResource::slowTooltipFor(ran('App\\Jobs\\Report', 120)))
        ->toBe(__('filament-jobs-monitor::translations.over_slow_threshold', ['seconds' => 60]))
        ->and(QueueMonitorResource::slowTooltipFor(ran('App\\Jobs\\Report', 1)))
        ->toBeNull();
});
