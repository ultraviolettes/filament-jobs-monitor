<?php

namespace Croustibat\FilamentJobsMonitor;

use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Where the plugin gets the list of queues to report on.
 *
 * `filament-jobs-monitor.queues` stays an explicit override. Left to null, the
 * queues are discovered from the sources that can actually be enumerated —
 * which is what makes Redis and SQS usable here, since neither can list its
 * queues, but every queue a job ever ran on is recorded in `queue_monitors`.
 */
class QueueDiscovery
{
    public const CACHE_KEY = 'filament-jobs-monitor.discovered-queues';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        $configured = config('filament-jobs-monitor.queues');

        if (filled($configured)) {
            return array_values(array_unique((array) $configured));
        }

        $ttl = (int) config('filament-jobs-monitor.queue_discovery.cache_ttl', 60);

        if ($ttl <= 0) {
            return static::discover();
        }

        return Cache::remember(static::CACHE_KEY, $ttl, static::discover(...));
    }

    /**
     * Forget the cached discovery, for tests and for `queue:restart`-style hooks.
     */
    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * @return array<int, string>
     */
    protected static function discover(): array
    {
        $queues = array_merge(
            static::fromMonitors(),
            static::fromQueueTables(),
            static::fromConnections(),
            static::fromHorizon(),
        );

        $queues = array_filter(array_map('strval', $queues), fn (string $queue): bool => $queue !== '');

        sort($queues);

        return array_values(array_unique($queues)) ?: ['default'];
    }

    /**
     * Every queue a monitored job ever ran on. The strongest signal, and the
     * only one available on Redis and SQS.
     *
     * @return array<int, string>
     */
    protected static function fromMonitors(): array
    {
        return static::rescue(fn (): array => array_keys(resolve(QueueMonitor::class)::distinctQueues()));
    }

    /**
     * @return array<int, string>
     */
    protected static function fromQueueTables(): array
    {
        $queues = [];

        if (resolve(QueueJob::class)::isSupported()) {
            $queues = array_merge($queues, static::distinct(resolve(QueueJob::class)));
        }

        return array_merge($queues, static::distinct(resolve(FailedJob::class)));
    }

    /**
     * The default queue of each configured connection.
     *
     * @return array<int, string>
     */
    protected static function fromConnections(): array
    {
        return array_values(array_filter(array_map(
            fn (array $connection): ?string => is_string($connection['queue'] ?? null) ? $connection['queue'] : null,
            array_filter((array) config('queue.connections', []), 'is_array'),
        )));
    }

    /**
     * Horizon enumerates every supervised queue in its defaults.
     *
     * @return array<int, string>
     */
    protected static function fromHorizon(): array
    {
        $queues = [];

        foreach ((array) config('horizon.defaults', []) as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
                $queues[] = $queue;
            }
        }

        return $queues;
    }

    /**
     * @param  Model  $model
     * @return array<int, string>
     */
    protected static function distinct($model): array
    {
        return static::rescue(function () use ($model): array {
            if (! Schema::connection($model->getConnectionName())->hasTable($model->getTable())) {
                return [];
            }

            return $model->newQuery()
                ->whereNotNull('queue')
                ->distinct()
                ->pluck('queue')
                ->all();
        });
    }

    /**
     * Discovery must never take the dashboard down: a connection the app
     * declares but cannot reach simply contributes nothing.
     *
     * @param  callable(): array<int, string>  $callback
     * @return array<int, string>
     */
    protected static function rescue(callable $callback): array
    {
        try {
            return $callback();
        } catch (Throwable) {
            return [];
        }
    }
}
