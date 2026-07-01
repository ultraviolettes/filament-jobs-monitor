<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Closure;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Number;

class QueueStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $driver = DB::connection()->getConfig('driver');

        $aggregationColumns = [
            DB::raw('COUNT(*) as count'),
            DB::raw($this->buildAggregateMode('SUM', 'finished_at', 'started_at', $driver).' as total_time_elapsed'),
            DB::raw($this->buildAggregateMode('AVG', 'finished_at', 'started_at', $driver).' as average_time_elapsed'),
        ];

        $aggregatedInfo = resolve(QueueMonitor::class)::query()
            ->select($aggregationColumns)
            ->first();

        $queueSize = collect(config('filament-jobs-monitor.queues') ?? ['default'])
            ->map(fn (string $queue): int => Queue::size($queue))
            ->sum();

        $totalJobs = Number::format($aggregatedInfo->count ?? 0);
        $executionTime = CarbonInterval::seconds((int) $aggregatedInfo->total_time_elapsed)->cascade()->forHumans(short: true, parts: 3);

        // Get job counts for the last 7 days for charts
        $jobsPerDay = $this->getJobsPerDay(7);
        $failedPerDay = $this->getFailedJobsPerDay(7);
        $succeededPerDay = $this->getSucceededJobsPerDay(7);

        $succeededCount = resolve(QueueMonitor::class)::whereNotNull('finished_at')->where('failed', false)->count();
        $failedCount = resolve(QueueMonitor::class)::whereNotNull('finished_at')->where('failed', true)->count();

        return [
            Stat::make(__('filament-jobs-monitor::translations.total_jobs'), $totalJobs)
                ->description(__('filament-jobs-monitor::translations.last_7_days'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($jobsPerDay)
                ->color('primary'),
            Stat::make(__('filament-jobs-monitor::translations.succeeded'), Number::format($succeededCount))
                ->description(__('filament-jobs-monitor::translations.completed_successfully'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart($succeededPerDay)
                ->color('success'),
            Stat::make(__('filament-jobs-monitor::translations.failed'), Number::format($failedCount))
                ->description($queueSize.' '.__('filament-jobs-monitor::translations.pending_in_queue'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->chart($failedPerDay)
                ->color($failedCount > 0 ? 'danger' : 'gray'),
            Stat::make(__('filament-jobs-monitor::translations.average_time'), ceil((float) $aggregatedInfo->average_time_elapsed).'s')
                ->description($executionTime.' '.__('filament-jobs-monitor::translations.total'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }

    private function getJobsPerDay(int $days): array
    {
        return $this->countPerDay($days);
    }

    private function getSucceededJobsPerDay(int $days): array
    {
        return $this->countPerDay($days, fn (Builder $query): Builder => $query
            ->whereNotNull('finished_at')
            ->where('failed', false));
    }

    private function getFailedJobsPerDay(int $days): array
    {
        return $this->countPerDay($days, fn (Builder $query): Builder => $query
            ->whereNotNull('finished_at')
            ->where('failed', true));
    }

    private function countPerDay(int $days, ?Closure $constrain = null): array
    {
        $query = resolve(QueueMonitor::class)::query()
            ->where('created_at', '>=', Carbon::now()->subDays($days - 1)->startOfDay());

        if ($constrain !== null) {
            $query = $constrain($query);
        }

        $countsByDay = $query
            ->get(['created_at'])
            ->countBy(fn (QueueMonitor $monitor): string => $monitor->created_at->toDateString());

        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $data[] = $countsByDay->get($date, 0);
        }

        return $data;
    }

    private function buildAggregateMode($mode, string $col1, string $col2, $driver = null): string
    {
        return sprintf(
            '%s(%s - %s)%s',
            $mode,
            $this->dbColumnAsInteger($col1),
            $this->dbColumnAsInteger($col2),
            ($driver === 'pgsql' ? '::int' : '')
        );
    }

    private function dbColumnAsInteger(string $colName): string
    {
        // Convert a datetime column to epoch seconds using each driver's own
        // function, so the duration aggregates work on SQLite/MySQL/Postgres
        // alike. Falling back to a raw column subtraction is wrong on SQLite
        // (string coercion yields 0) — see issue #55.
        return match (DB::connection()->getConfig('driver')) {
            'pgsql' => sprintf('CAST(EXTRACT(EPOCH FROM %s) AS INTEGER)', $colName),
            'sqlite' => "CAST(strftime('%s', {$colName}) AS INTEGER)",
            'mysql', 'mariadb' => sprintf('UNIX_TIMESTAMP(%s)', $colName),
            default => $colName,
        };
    }
}
