<?php

namespace Croustibat\FilamentJobsMonitor\Events;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A monitored job finished, and took unusually long.
 *
 * `reason` is `threshold` when the run crossed the absolute limit, or `anomaly`
 * when it took more than the configured multiple of its class median.
 */
class JobMonitorSlow
{
    use Dispatchable;

    public function __construct(
        public QueueMonitor $monitor,
        public string $reason,
        public int $duration,
        public ?float $median = null,
        public ?float $ratio = null,
    ) {}
}
