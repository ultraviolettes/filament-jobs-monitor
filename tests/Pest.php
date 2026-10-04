<?php

use Croustibat\FilamentJobsMonitor\Chains;
use Croustibat\FilamentJobsMonitor\QueueMonitorProvider;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\SlowJobs;
use Croustibat\FilamentJobsMonitor\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Schema support is memoized on purpose, so a long-running worker checks it
 * once per process — but the suite runs every test in the same process, with a
 * different schema each time. Reset it, along with the other process state the
 * package keeps, so test files cannot leak into each other.
 */
uses()->beforeEach(function () {
    foreach (['supportsBatchTracking', 'supportsFailureTracking'] as $property) {
        (new ReflectionProperty(QueueMonitorProvider::class, $property))->setValue(null, null);
    }

    (new ReflectionProperty(QueueMonitorResource::class, 'tracksBatches'))->setValue(null, null);

    Chains::leave();
    SlowJobs::flush();
})->in(__DIR__);
