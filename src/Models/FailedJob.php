<?php

namespace Croustibat\FilamentJobsMonitor\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('queue.failed.database')
            ?? config('database.default');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        $column = config('filament-jobs-monitor.tenancy.column', 'tenant_id');

        return $query->where('payload', 'LIKE', '%"'.$column.'";i:'.$tenantId.';%');
    }
}
