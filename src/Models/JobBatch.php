<?php

namespace Croustibat\FilamentJobsMonitor\Models;

use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * A row of Laravel's `job_batches` table.
 *
 * Read only: the batch state belongs to Laravel, so the page reads it instead
 * of duplicating it, and acts through the `Batch` object itself.
 *
 * @property string $id
 * @property string|null $name
 * @property int $total_jobs
 * @property int $pending_jobs
 * @property int $failed_jobs
 * @property string $failed_job_ids
 * @property int|null $cancelled_at
 * @property int $created_at
 * @property int|null $finished_at
 */
class JobBatch extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function getTable()
    {
        return config('queue.batching.table', 'job_batches');
    }

    public function getConnectionName(): ?string
    {
        return config('queue.batching.database')
            ?? config('database.default');
    }

    /**
     * Whether batches can be listed at all: Laravel only stores them with the
     * database driver, and the table has to have been migrated.
     */
    public static function isSupported(): bool
    {
        if (config('queue.batching.driver', 'database') !== 'database') {
            return false;
        }

        try {
            $model = resolve(static::class);

            return Schema::connection($model->getConnectionName())->hasTable($model->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The jobs of this batch that the plugin monitored.
     *
     * @return Builder<QueueMonitor>
     */
    public function monitors(): Builder
    {
        return resolve(QueueMonitor::class)::query()
            ->where('batch_id', $this->id)
            ->orderBy('started_at');
    }

    /**
     * Laravel's own batch object, which carries the actions.
     */
    public function toBatch(): ?Batch
    {
        return Bus::findBatch($this->id);
    }

    public function displayName(): string
    {
        return filled($this->name)
            ? $this->name
            : __('filament-jobs-monitor::translations.unnamed_batch', ['id' => Str::limit($this->id, 8, '')]);
    }

    public function processedJobs(): int
    {
        return $this->total_jobs - $this->pending_jobs;
    }

    public function progress(): int
    {
        return $this->total_jobs > 0
            ? (int) round(($this->processedJobs() / $this->total_jobs) * 100)
            : 0;
    }

    /**
     * pending · processing · finished · cancelled · failed
     */
    public function status(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }

        if ($this->failed_jobs > 0 && $this->pending_jobs === 0) {
            return 'failed';
        }

        if ($this->finished_at !== null) {
            return 'finished';
        }

        return $this->processedJobs() > 0 ? 'processing' : 'pending';
    }

    public function startedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->created_at);
    }

    public function finishedAt(): ?Carbon
    {
        return $this->finished_at === null ? null : Carbon::createFromTimestamp($this->finished_at);
    }

    /**
     * Batches of the current tenant only, identified through the jobs the
     * plugin monitored: a batch has no tenant of its own.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTenant(Builder $query, string|int $tenantId): Builder
    {
        $column = config('filament-jobs-monitor.tenancy.column', 'tenant_id');

        return $query->whereIn($this->getKeyName(), fn ($sub) => $sub
            ->select('batch_id')
            ->from(resolve(QueueMonitor::class)->getTable())
            ->whereNotNull('batch_id')
            ->where($column, $tenantId));
    }
}
