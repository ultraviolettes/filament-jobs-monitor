<?php

use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\QueueMonitorProvider;
use Illuminate\Contracts\Queue\Job as JobContract;

beforeEach(function () {
    config()->set('filament-jobs-monitor.tenancy.enabled', false);
    config()->set('filament-jobs-monitor.tenancy.model', null);
    config()->set('filament-jobs-monitor.tenancy.column', 'tenant_id');
});

it('returns null tenant id when tenancy is disabled', function () {
    config()->set('filament-jobs-monitor.tenancy.enabled', false);

    $job = createMockJobWithTenantId(123);
    $tenantId = invokeTenantIdExtraction($job);

    expect($tenantId)->toBeNull();
});

it('extracts tenant id from job payload when tenancy is enabled', function () {
    config()->set('filament-jobs-monitor.tenancy.enabled', true);

    $job = createMockJobWithTenantId(456);
    $tenantId = invokeTenantIdExtraction($job);

    expect($tenantId)->toBe(456);
});

it('returns null when job has no tenant id property', function () {
    config()->set('filament-jobs-monitor.tenancy.enabled', true);

    $job = createMockJobWithoutTenantId();
    $tenantId = invokeTenantIdExtraction($job);

    expect($tenantId)->toBeNull();
});

it('returns null when job payload has no command', function () {
    config()->set('filament-jobs-monitor.tenancy.enabled', true);

    $job = Mockery::mock(JobContract::class);
    $job->shouldReceive('payload')->andReturn(['data' => []]);

    $tenantId = invokeTenantIdExtraction($job);

    expect($tenantId)->toBeNull();
});

it('returns null when command cannot be unserialized', function () {
    config()->set('filament-jobs-monitor.tenancy.enabled', true);

    $job = Mockery::mock(JobContract::class);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => 'invalid-serialized-data'],
    ]);

    $tenantId = invokeTenantIdExtraction($job);

    expect($tenantId)->toBeNull();
});

it('QueueJob forTenant scope filters by tenant id in payload', function () {
    $tenantId = 789;

    $query = QueueJob::query()->forTenant($tenantId);
    $sql = $query->toSql();

    expect($sql)->toContain('payload');
    expect($sql)->toContain('LIKE');
});

it('FailedJob forTenant scope filters by tenant id in payload', function () {
    $tenantId = 101;

    $query = FailedJob::query()->forTenant($tenantId);
    $sql = $query->toSql();

    expect($sql)->toContain('payload');
    expect($sql)->toContain('LIKE');
});

/*
|--------------------------------------------------------------------------
| Helper Classes & Functions
|--------------------------------------------------------------------------
*/

class TenantJobStub
{
    public function __construct(public int $tenantId) {}
}

class NonTenantJobStub
{
    public string $someProperty = 'value';
}

function createMockJobWithTenantId(int $tenantId): JobContract
{
    $command = new TenantJobStub($tenantId);

    $job = Mockery::mock(JobContract::class);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => serialize($command)],
    ]);

    return $job;
}

function createMockJobWithoutTenantId(): JobContract
{
    $command = new NonTenantJobStub;

    $job = Mockery::mock(JobContract::class);
    $job->shouldReceive('payload')->andReturn([
        'data' => ['command' => serialize($command)],
    ]);

    return $job;
}

function invokeTenantIdExtraction(JobContract $job): ?int
{
    $reflection = new ReflectionClass(QueueMonitorProvider::class);
    $method = $reflection->getMethod('getTenantIdFromJob');
    $method->setAccessible(true);

    return $method->invoke(null, $job);
}
