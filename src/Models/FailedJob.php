<?php

namespace Croustibat\FilamentJobsMonitor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A row of Laravel's `failed_jobs` table.
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property array<string, mixed> $payload
 * @property string $exception
 * @property Carbon $failed_at
 */
class FailedJob extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
        ];
    }

    public function getTable()
    {
        return config('queue.failed.table', 'failed_jobs');
    }

    public function getConnectionName(): ?string
    {
        return config('queue.failed.database')
            ?? config('database.default');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        $column = config('filament-jobs-monitor.tenancy.column', 'tenant_id');
        $strlenTenantId = strlen($tenantId);

        return $query->where('payload', 'LIKE', '%"'.$column.'";s:'.$strlenTenantId.':"'.$tenantId.'";%');
    }
}
