<?php

namespace Croustibat\FilamentJobsMonitor\Models;

use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class QueueMonitor extends Model
{
    use HasFactory, Prunable;

    public function getConnectionName()
    {
        return config('filament-jobs-monitor.connection') ?? parent::getConnectionName();
    }

    protected $fillable = [
        'job_id',
        'name',
        'queue',
        'started_at',
        'finished_at',
        'failed',
        'attempt',
        'progress',
        'exception_message',
        'tenant_id',
    ];

    protected $casts = [
        'failed' => 'bool',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (! config('filament-jobs-monitor.tenancy.enabled')) {
                return;
            }

            if (! class_exists(\Filament\Facades\Filament::class)) {
                return;
            }

            if (! app()->bound('filament')) {
                return;
            }

            $tenant = \Filament\Facades\Filament::getTenant();

            if ($tenant) {
                $column = config('filament-jobs-monitor.tenancy.column', 'tenant_id');
                $query->where($column, $tenant->getKey());
            }
        });
    }

    /*
     *--------------------------------------------------------------------------
     * Relationships
     *--------------------------------------------------------------------------
     */

    public function tenant(): BelongsTo
    {
        $model = config('filament-jobs-monitor.tenancy.model');
        $column = config('filament-jobs-monitor.tenancy.column', 'tenant_id');

        return $this->belongsTo($model, $column);
    }

    /*
     *--------------------------------------------------------------------------
     * Mutators
     *--------------------------------------------------------------------------
     */
    public function status(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->isFinished()) {
                    return $this->failed ? 'failed' : 'succeeded';
                }

                return 'running';
            },
        );
    }

    /*
     *--------------------------------------------------------------------------
     * Methods
     *--------------------------------------------------------------------------
     */

    public static function getJobId(JobContract $job): string|int
    {
        return $job->payload()['uuid'] ?? Hash::make($job->getRawBody());
    }

    /**
     * check if the job is finished.
     */
    public function isFinished(): bool
    {
        if ($this->hasFailed()) {
            return true;
        }

        return $this->finished_at !== null;
    }

    /**
     * Check if the job has failed.
     */
    public function hasFailed(): bool
    {
        return $this->failed;
    }

    /**
     * check if the job has succeeded.
     */
    public function hasSucceeded(): bool
    {
        if (! $this->isFinished()) {
            return false;
        }

        return ! $this->hasFailed();
    }

    /**
     * Get the prunable model query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function prunable(): Builder|bool
    {
        if (FilamentJobsMonitorPlugin::get()->getPruning()) {
            return static::where('created_at', '<=', now()->subDays(FilamentJobsMonitorPlugin::get()->getPruningRetention()));
        }

        return false;
    }
}
