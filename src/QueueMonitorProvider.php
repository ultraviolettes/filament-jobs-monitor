<?php

namespace Croustibat\FilamentJobsMonitor;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
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
    protected static function getTenantIdFromJob(JobContract $job): ?string
    {
        if (! config('filament-jobs-monitor.tenancy.enabled')) {
            return null;
        }

        $payload = $job->payload();

        if (! isset($payload['data']['command'])) {
            return null;
        }

        try {
            $command = unserialize($payload['data']['command']);

            return $command->tenantId ?? null;
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

        $monitor = resolve(QueueMonitor::class)::on(self::getConnection())->create([
            'job_id' => $jobId,
            'name' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'started_at' => $now,
            'attempt' => $job->attempts(),
            'progress' => 0,
            'tenant_id' => $tenantId,
        ]);

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

        $attributes = [
            'progress' => 100,
            'finished_at' => now(),
            'failed' => $failed,
        ];

        if ($exception !== null) {
            $attributes += [
                'exception_message' => mb_strcut($exception->getMessage(), 0, 65535),
            ];
        }

        $monitor->update($attributes);
    }
}
