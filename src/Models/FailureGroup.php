<?php

namespace Croustibat\FilamentJobsMonitor\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $signature
 * @property string $exception_class
 * @property string|null $job_class
 * @property string|null $queue
 * @property string|null $message
 * @property int $occurrences_count
 * @property Carbon|null $first_occurred_at
 * @property Carbon|null $last_occurred_at
 * @property Carbon|null $resolved_at
 * @property string|null $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FailureGroup extends Model
{
    protected $table = 'queue_monitor_failure_groups';

    public function getConnectionName()
    {
        return config('filament-jobs-monitor.connection') ?? parent::getConnectionName();
    }

    protected $fillable = [
        'signature',
        'exception_class',
        'job_class',
        'queue',
        'message',
        'occurrences_count',
        'first_occurred_at',
        'last_occurred_at',
        'resolved_at',
        'tenant_id',
    ];

    protected $casts = [
        'first_occurred_at' => 'datetime',
        'last_occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Daily failure counts, memoized per number of days.
     *
     * @var array<int, array<int, int>>
     */
    protected array $cachedDailyCounts = [];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (! config('filament-jobs-monitor.tenancy.enabled')) {
                return;
            }

            if (! class_exists(Filament::class)) {
                return;
            }

            if (! app()->bound('filament')) {
                return;
            }

            $tenant = Filament::getTenant();

            if ($tenant) {
                $query->where('tenant_id', $tenant->getKey());
            }
        });
    }

    /*
     *--------------------------------------------------------------------------
     * Relationships
     *--------------------------------------------------------------------------
     */

    /**
     * @return HasMany<QueueMonitor, $this>
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(resolve(QueueMonitor::class)::class, 'failure_signature', 'signature');
    }

    /*
     *--------------------------------------------------------------------------
     * Mutators
     *--------------------------------------------------------------------------
     */

    public function status(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isResolved() ? 'resolved' : 'open',
        );
    }

    /*
     *--------------------------------------------------------------------------
     * Methods
     *--------------------------------------------------------------------------
     */

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function markResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }

    public function reopen(): void
    {
        $this->update(['resolved_at' => null]);
    }

    /**
     * Normalise an exception message so that messages differing only by
     * dynamic values (ids, uuids, hashes, quoted strings) share a signature.
     */
    public static function normalizeMessage(string $message): string
    {
        $normalized = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "'<str>'", $message);
        $normalized = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '"<str>"', $normalized);
        $normalized = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '<uuid>', $normalized);
        $normalized = preg_replace('/\b[0-9a-f]{16,}\b/i', '<hash>', $normalized);
        $normalized = preg_replace('/\b\d+(\.\d+)?\b/', '<num>', $normalized);

        return mb_strcut(trim($normalized), 0, 1024);
    }

    /**
     * Build the stable signature of a failure group.
     */
    public static function makeSignature(string $exceptionClass, ?string $jobClass, string $normalizedMessage): string
    {
        return hash('sha256', $exceptionClass.'|'.($jobClass ?? '').'|'.$normalizedMessage);
    }

    /**
     * Record a failure occurrence: create or update its group and return it.
     * A new occurrence reopens a previously resolved group.
     */
    public static function recordFailure(
        string $exceptionClass,
        ?string $jobClass,
        ?string $queue,
        string $message,
        null|int|string $tenantId = null,
    ): static {
        $normalized = static::normalizeMessage($message);
        $signature = static::makeSignature($exceptionClass, $jobClass, $normalized);

        /** @var static $group */
        $group = static::query()
            ->withoutGlobalScope('tenant')
            ->firstOrNew(['signature' => $signature]);

        $group->fill([
            'exception_class' => $exceptionClass,
            'job_class' => $jobClass,
            'queue' => $queue,
            'message' => $normalized,
            'tenant_id' => $tenantId ?? $group->tenant_id,
        ]);

        $group->occurrences_count = ($group->occurrences_count ?? 0) + 1;
        $group->first_occurred_at ??= now();
        $group->last_occurred_at = now();
        $group->resolved_at = null;
        $group->save();

        return $group;
    }

    /**
     * Failure counts per day over the given window, oldest day first.
     *
     * @return array<int, int>
     */
    public function dailyCounts(int $days = 7): array
    {
        if (isset($this->cachedDailyCounts[$days])) {
            return $this->cachedDailyCounts[$days];
        }

        $start = now()->subDays($days - 1)->startOfDay();

        $counts = resolve(QueueMonitor::class)::query()
            ->withoutGlobalScope('tenant')
            ->where('failure_signature', $this->signature)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[] = (int) ($counts[$start->copy()->addDays($i)->toDateString()] ?? 0);
        }

        return $this->cachedDailyCounts[$days] = $series;
    }

    /**
     * Short class name of the exception (without namespace).
     */
    public function getShortExceptionClassAttribute(): string
    {
        return class_basename($this->exception_class ?? '');
    }
}
