<?php

use Croustibat\FilamentJobsMonitor\Authorization;
use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListFailures;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListQueueMonitors;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * A policy covering a single ability, to prove that an ability the policy does
 * not implement still falls through to the gate and then to the fallback.
 */
class ViewOnlyQueueMonitorPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }
}

const ABILITIES = [
    Authorization::VIEW_ANY,
    Authorization::RETRY,
    Authorization::DELETE,
    Authorization::CLEAR_LOGS,
    Authorization::DELETE_PENDING_JOB,
    Authorization::RESOLVE_FAILURE,
];

it('denies every ability when nothing is configured', function (string $ability) {
    expect(Authorization::allows($ability))->toBeFalse()
        ->and(Authorization::denies($ability))->toBeTrue();
})->with(ABILITIES);

it('grants every ability when the fallback is turned back on', function (string $ability) {
    config()->set('filament-jobs-monitor.authorization.fallback', true);

    expect(Authorization::allows($ability))->toBeTrue();
})->with(ABILITIES);

it('reads a gate of the same name', function () {
    Gate::define(Authorization::CLEAR_LOGS, fn (?Authenticatable $user) => true);

    expect(Authorization::allows(Authorization::CLEAR_LOGS))->toBeTrue()
        ->and(Authorization::allows(Authorization::DELETE))->toBeFalse();
});

it('lets a gate deny an ability the fallback would have granted', function () {
    config()->set('filament-jobs-monitor.authorization.fallback', true);
    Gate::define(Authorization::CLEAR_LOGS, fn (?Authenticatable $user) => false);

    expect(Authorization::allows(Authorization::CLEAR_LOGS))->toBeFalse()
        ->and(Authorization::allows(Authorization::DELETE))->toBeTrue();
});

it('reads a policy registered on the monitor model', function () {
    Gate::policy(QueueMonitor::class, ViewOnlyQueueMonitorPolicy::class);

    expect(Authorization::allows(Authorization::VIEW_ANY))->toBeTrue();
});

it('falls through for an ability the policy does not implement', function () {
    config()->set('filament-jobs-monitor.authorization.fallback', true);
    Gate::policy(QueueMonitor::class, ViewOnlyQueueMonitorPolicy::class);

    // `clearLogs` is absent from the policy, so the fallback decides.
    expect(Authorization::allows(Authorization::CLEAR_LOGS))->toBeTrue();
});

it('keeps the plugin callbacks, which take precedence over everything else', function () {
    $plugin = FilamentJobsMonitorPlugin::make()
        ->authorize(false)
        ->authorizeRetry(fn () => true)
        ->authorizeClearLogs(true);

    expect($plugin->getAuthorization(Authorization::VIEW_ANY))->toBeFalse()
        ->and($plugin->getAuthorization(Authorization::CLEAR_LOGS))->toBeTrue()
        ->and($plugin->getAuthorization(Authorization::RETRY))->toBeInstanceOf(Closure::class)
        ->and($plugin->getAuthorization(Authorization::DELETE))->toBeNull();
});

it('hides the resource and its pages from unauthorized users', function () {
    expect(QueueMonitorResource::canViewAny())->toBeFalse()
        ->and(QueueMonitorResource::canDeleteAny())->toBeFalse()
        ->and(ListQueueMonitors::canAccess())->toBeFalse()
        ->and(ListFailures::canAccess())->toBeFalse();
});

it('opens the resource and its pages once the ability is granted', function () {
    Gate::define(Authorization::VIEW_ANY, fn (?Authenticatable $user) => true);

    expect(QueueMonitorResource::canViewAny())->toBeTrue()
        ->and(ListQueueMonitors::canAccess())->toBeTrue()
        ->and(ListFailures::canAccess())->toBeTrue()
        ->and(QueueMonitorResource::canDeleteAny())->toBeFalse();
});

it('keeps the Failures page closed when the feature is disabled, even when authorized', function () {
    config()->set('filament-jobs-monitor.failures.enabled', false);
    Gate::define(Authorization::VIEW_ANY, fn (?Authenticatable $user) => true);

    expect(ListFailures::canAccess())->toBeFalse();
});
