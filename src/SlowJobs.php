<?php

namespace Croustibat\FilamentJobsMonitor;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Detects jobs that take unusually long.
 *
 * A run is slow when it crosses the absolute threshold, or when it takes more
 * than `anomaly_multiplier` times the median of its own job class — the latter
 * only once that class has `min_samples` finished runs, so a class seen three
 * times cannot flag itself.
 *
 * Medians and percentiles are computed in PHP from a single grouped-free query:
 * SQLite and MySQL have no portable percentile function, and one query over the
 * retention window keeps the cost flat whatever the number of job classes.
 */
class SlowJobs
{
    public const CACHE_KEY = 'filament-jobs-monitor.slow-baselines';

    public static function enabled(): bool
    {
        return (bool) config('filament-jobs-monitor.slow.enabled', true);
    }

    public static function thresholdSeconds(): ?int
    {
        $threshold = config('filament-jobs-monitor.slow.threshold_seconds', 60);

        return $threshold === null ? null : (int) $threshold;
    }

    /**
     * Is this run slow, and why?
     *
     * @return array{slow: bool, reason: 'threshold'|'anomaly'|null, duration: int|null, median: float|null, ratio: float|null}
     */
    public static function inspect(QueueMonitor $record): array
    {
        $none = ['slow' => false, 'reason' => null, 'duration' => null, 'median' => null, 'ratio' => null];

        if (! static::enabled() || ! $record->started_at || ! $record->finished_at) {
            return $none;
        }

        $duration = $record->finished_at->getTimestamp() - $record->started_at->getTimestamp();
        $threshold = static::thresholdSeconds();
        $baseline = $record->name === null ? null : (static::baselines()[$record->name] ?? null);
        $median = $baseline['median'] ?? null;
        $ratio = $median > 0 ? round($duration / $median, 1) : null;

        if ($median !== null
            && $baseline['runs'] >= (int) config('filament-jobs-monitor.slow.min_samples', 20)
            && $duration > $median * (float) config('filament-jobs-monitor.slow.anomaly_multiplier', 2.0)
        ) {
            return ['slow' => true, 'reason' => 'anomaly', 'duration' => $duration, 'median' => $median, 'ratio' => $ratio];
        }

        if ($threshold !== null && $duration >= $threshold) {
            return ['slow' => true, 'reason' => 'threshold', 'duration' => $duration, 'median' => $median, 'ratio' => $ratio];
        }

        return $none + ['duration' => $duration, 'median' => $median, 'ratio' => $ratio];
    }

    public static function isSlow(QueueMonitor $record): bool
    {
        return static::inspect($record)['slow'];
    }

    /**
     * Per class statistics over the retention window, keyed by job class.
     *
     * @return array<string, array{runs: int, median: float, p95: float, previous_median: float|null}>
     */
    public static function baselines(): array
    {
        $ttl = (int) config('filament-jobs-monitor.slow.cache_ttl', 300);

        if ($ttl <= 0) {
            return static::computeBaselines();
        }

        return Cache::remember(static::CACHE_KEY, $ttl, static::computeBaselines(...));
    }

    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * The slowest classes first, for the widget.
     *
     * @return array<int, array{name: string, runs: int, median: float, p95: float, previous_median: float|null, trend: float|null}>
     */
    public static function slowest(int $limit = 5): array
    {
        $rows = [];

        foreach (static::baselines() as $name => $stats) {
            $rows[] = [
                'name' => $name,
                'runs' => $stats['runs'],
                'median' => $stats['median'],
                'p95' => $stats['p95'],
                'previous_median' => $stats['previous_median'],
                'trend' => $stats['previous_median'] > 0
                    ? round(($stats['median'] - $stats['previous_median']) / $stats['previous_median'], 2)
                    : null,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['median'] <=> $a['median']);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<string, array{runs: int, median: float, p95: float, previous_median: float|null}>
     */
    protected static function computeBaselines(): array
    {
        $days = (int) config('filament-jobs-monitor.slow.window_days', 7);
        $window = now()->subDays($days);
        $previousWindow = now()->subDays($days * 2);

        $elapsed = resolve(QueueMonitor::class)::elapsedSeconds();

        /** @var Collection<int, object{name: string|null, seconds: int|float|null, started_at: mixed}> $runs */
        $runs = resolve(QueueMonitor::class)::query()
            ->selectRaw("name, started_at, {$elapsed} as seconds")
            ->whereNotNull('name')
            ->whereNotNull('finished_at')
            ->where('failed', false)
            ->where('started_at', '>=', $previousWindow)
            ->get();

        $baselines = [];

        foreach ($runs->groupBy('name') as $name => $classRuns) {
            $current = $classRuns->filter(fn ($run): bool => $run->started_at >= $window)
                ->pluck('seconds')
                ->map(fn ($seconds): float => (float) $seconds)
                ->values();

            if ($current->isEmpty()) {
                continue;
            }

            $previous = $classRuns->filter(fn ($run): bool => $run->started_at < $window)
                ->pluck('seconds')
                ->map(fn ($seconds): float => (float) $seconds)
                ->values();

            $baselines[(string) $name] = [
                'runs' => $current->count(),
                'median' => static::percentile($current, 0.5),
                'p95' => static::percentile($current, 0.95),
                'previous_median' => $previous->isEmpty() ? null : static::percentile($previous, 0.5),
            ];
        }

        return $baselines;
    }

    /**
     * Nearest-rank percentile, which needs no interpolation and no SQL support.
     *
     * @param  Collection<int, float>  $values
     */
    protected static function percentile(Collection $values, float $percentile): float
    {
        $sorted = $values->sort()->values();
        $rank = (int) ceil($percentile * $sorted->count()) - 1;

        return (float) $sorted[max($rank, 0)];
    }
}
