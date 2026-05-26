<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
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
        $executionTime = CarbonInterval::seconds($aggregatedInfo->total_time_elapsed ?? 0)->cascade()->forHumans(short: true, parts: 3);

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
        $data = [];
        $model = resolve(QueueMonitor::class);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $data[] = $model::whereDate('created_at', $date)->count();
        }

        return $data;
    }

    private function getSucceededJobsPerDay(int $days): array
    {
        $data = [];
        $model = resolve(QueueMonitor::class);

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $data[] = $model::whereDate('created_at', $date)
                ->whereNotNull('finished_at')
                ->where('failed', false)
                ->count();
        }

        return $data;
    }

    private function getFailedJobsPerDay(int $days): array
    {
        $data = [];
        $model = resolve(QueueMonitor::class);
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $data[] = $model::whereDate('created_at', $date)
                ->whereNotNull('finished_at')
                ->where('failed', true)
                ->count();
        }

        return $data;
    }

    private function buildAggregateMode($mode, string $col1, string $col2, $driver = null): string
    {
        return sprintf(
            '%s(%s - %s)%s',
            $mode,
            $this->dbColumnAsInteger($col1, $driver),
            $this->dbColumnAsInteger($col2, $driver),
            ($driver === 'pgsql' ? '::int' : '')
        );
    }

    private function dbColumnAsInteger(string $colName, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getConfig('driver');

        return match ($driver) {
            'pgsql' => sprintf('CAST(EXTRACT(EPOCH FROM %s) AS INTEGER)', $colName),
            'sqlite' => sprintf("CAST(strftime('%%s', %s) AS INTEGER)", $colName),
            'sqlsrv' => sprintf("DATEDIFF(SECOND, '1970-01-01 00:00:00', %s)", $colName),
            default => sprintf('UNIX_TIMESTAMP(%s)', $colName),
        };
    }
}
