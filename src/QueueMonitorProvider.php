<?php

namespace Croustibat\FilamentJobsMonitor;

use Croustibat\FilamentJobsMonitor\Models\FailureGroup;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class QueueMonitorProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Queue::before(static function (JobProcessing $event) {
            self::jobStarted($event->job);
        });

        Queue::after(static function (JobProcessed $event) {
            self::jobFinished($event->job);
        });

        Queue::failing(static function (JobFailed $event) {
            self::jobFinished($event->job, true, $event->exception);
        });

        Queue::exceptionOccurred(static function (JobExceptionOccurred $event) {
            self::jobFinished($event->job, true, $event->exception);
        });
    }

    protected static function getConnection(): string
    {
        return config('filament-jobs-monitor.connection') ?? config('database.default');
    }

    /**
     * Get Job ID.
     */
    public static function getJobId(JobContract $job): string|int
    {
        return resolve(QueueMonitor::class)::getJobId($job);
    }

    /**
     * Extract tenant ID from job payload.
     */
    protected static function getTenantIdFromJob(JobContract $job): null|int|string
    {
        if (! config('filament-jobs-monitor.tenancy.enabled')) {
            return null;
        }

        $payload = $job->payload();

        // Try and get the tenantId from the payload key.
        $payloadKey = config('filament-jobs-monitor.tenancy.payload_key', 'tenant_id');
        if (isset($payload[$payloadKey])) {
            return $payload[$payloadKey];
        }

        if (! isset($payload['data']['command'])) {
            return null;
        }

        try {
            $command = unserialize($payload['data']['command']);

            // Regular job: check for tenantId property on the command itself
            if (property_exists($command, 'tenantId')) {
                return $command->tenantId;
            }

            // Queued event listener: extract tenantId from the event passed to the listener
            if ($command instanceof CallQueuedListener) {
                $event = $command->data[0] ?? null;
                if ($event && property_exists($event, 'tenantId')) {
                    return $event->tenantId;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Start Queue Monitoring for Job.
     */
    protected static function jobStarted(JobContract $job): void
    {
        $now = now();
        $jobId = self::getJobId($job);
        $tenantId = self::getTenantIdFromJob($job);

        $attributes = [
            'job_id' => $jobId,
            'name' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'started_at' => $now,
            'attempt' => $job->attempts(),
            'progress' => 0,
        ];

        if (config('filament-jobs-monitor.tenancy.enabled')) {
            $attributes['tenant_id'] = $tenantId;
        }

        $monitor = resolve(QueueMonitor::class)::on(self::getConnection())->create($attributes);

        resolve(QueueMonitor::class)::on(self::getConnection())
            ->where('id', '!=', $monitor->id)
            ->where('job_id', $jobId)
            ->where('failed', false)
            ->whereNull('finished_at')
            ->each(function ($monitor) {
                $monitor->finished_at = now();
                $monitor->failed = true;
                $monitor->save();
            });
    }

    /**
     * Finish Queue Monitoring for Job.
     */
    protected static function jobFinished(JobContract $job, bool $failed = false, ?\Throwable $exception = null): void
    {
        $monitor = resolve(QueueMonitor::class)::on(self::getConnection())
            ->where('job_id', self::getJobId($job))
            ->where('attempt', $job->attempts())
            ->orderByDesc('started_at')
            ->first();

        if ($monitor === null) {
            return;
        }

        // Laravel fires both JobExceptionOccurred and JobFailed for the same
        // failure — only count the occurrence once per monitor row.
        $alreadyRecordedAsFailed = $monitor->failed && $monitor->finished_at !== null;

        $attributes = [
            'progress' => 100,
            'finished_at' => now(),
            'failed' => $failed,
        ];

        if ($exception !== null) {
            $attributes += [
                'exception_message' => mb_strcut($exception->getMessage(), 0, 65535),
            ];

            if (self::supportsFailureTracking()) {
                $attributes += [
                    'exception_class' => $exception::class,
                    // Full string cast: includes the throw location
                    // ("Class: message in /path/file.php:42") before the trace.
                    'exception' => mb_strcut((string) $exception, 0, 65535),
                ];

                if ($failed && ! $alreadyRecordedAsFailed) {
                    try {
                        $group = resolve(FailureGroup::class)::recordFailure(
                            exceptionClass: $exception::class,
                            jobClass: $monitor->name,
                            queue: $monitor->queue,
                            message: $exception->getMessage(),
                            tenantId: $monitor->tenant_id,
                        );

                        $attributes['failure_signature'] = $group->signature;
                    } catch (\Throwable) {
                        // Never let failure grouping break job processing.
                    }
                }
            }
        }

        $monitor->update($attributes);
    }

    /**
     * Whether the failure-tracking migration has been run. Memoized so
     * long-running workers only hit the schema once per process.
     */
    protected static ?bool $supportsFailureTracking = null;

    protected static function supportsFailureTracking(): bool
    {
        if (self::$supportsFailureTracking !== null) {
            return self::$supportsFailureTracking;
        }

        try {
            $schema = Schema::connection(config('filament-jobs-monitor.connection'));

            self::$supportsFailureTracking = $schema->hasColumn('queue_monitors', 'failure_signature')
                && $schema->hasTable('queue_monitor_failure_groups');
        } catch (\Throwable) {
            self::$supportsFailureTracking = false;
        }

        return self::$supportsFailureTracking;
    }
}
