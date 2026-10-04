<?php

namespace Croustibat\FilamentJobsMonitor;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Authorization for the abilities the plugin exposes.
 *
 * Resolution order, first match wins:
 *
 *   1. the closure configured on the plugin (`authorize()`, `authorizeRetry()`, …)
 *   2. a policy registered for the `QueueMonitor` model, when it implements the ability
 *   3. a gate of the same name
 *   4. the `authorization.fallback` config key — `true` by default, so upgrading
 *      changes nothing until the application opts into the stricter behaviour
 */
class Authorization
{
    public const VIEW_ANY = 'viewAnyQueueMonitor';

    public const RETRY = 'retryQueueMonitor';

    public const DELETE = 'deleteQueueMonitor';

    public const CLEAR_LOGS = 'clearQueueMonitorLogs';

    public const DELETE_PENDING_JOB = 'deletePendingJob';

    public const RESOLVE_FAILURE = 'resolveQueueMonitorFailure';

    /**
     * The policy method each ability maps to.
     *
     * @var array<string, string>
     */
    protected const POLICY_METHODS = [
        self::VIEW_ANY => 'viewAny',
        self::RETRY => 'retry',
        self::DELETE => 'delete',
        self::CLEAR_LOGS => 'clearLogs',
        self::DELETE_PENDING_JOB => 'deletePendingJob',
        self::RESOLVE_FAILURE => 'resolveFailure',
    ];

    public static function allows(string $ability, ?Model $record = null): bool
    {
        $configured = static::configured($ability);

        if ($configured !== null) {
            return (bool) (is_callable($configured) ? $configured($record) : $configured);
        }

        $model = resolve(QueueMonitor::class)::class;
        $method = static::POLICY_METHODS[$ability] ?? null;
        $policy = Gate::getPolicyFor($model);

        if ($policy !== null && $method !== null && method_exists($policy, $method)) {
            return Gate::allows($method, $record ?? $model);
        }

        if (Gate::has($ability)) {
            return $record !== null
                ? Gate::allows($ability, $record)
                : Gate::allows($ability);
        }

        return (bool) config('filament-jobs-monitor.authorization.fallback', true);
    }

    public static function denies(string $ability, ?Model $record = null): bool
    {
        return ! static::allows($ability, $record);
    }

    /**
     * The closure or boolean configured on the plugin, if any.
     *
     * The plugin is only reachable inside a Filament panel; everywhere else
     * (console commands, queued jobs, tests without a panel) the policy, the
     * gate and the fallback still apply.
     */
    protected static function configured(string $ability): bool|callable|null
    {
        try {
            return FilamentJobsMonitorPlugin::get()->getAuthorization($ability);
        } catch (Throwable) {
            return null;
        }
    }
}
