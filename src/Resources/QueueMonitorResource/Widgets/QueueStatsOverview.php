<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class QueueStatsOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $driver = DB::connection()->getConfig('driver');

        $aggregationColumns = [
            DB::raw('COUNT(*) as count'),
            DB::raw($this->buildAggregateMode('SUM', 'finished_at', 'started_at', $driver) . ' as total_time_elapsed'),
            DB::raw($this->buildAggregateMode('AVG', 'finished_at', 'started_at', $driver) . ' as average_time_elapsed'),
        ];

        $aggregatedInfo = QueueMonitor::query()
            ->select($aggregationColumns)
            ->first();

        $queueSize = collect(config('filament-jobs-monitor.queues') ?? ['default'])
            ->map(fn(string $queue): int => Queue::size($queue))
            ->sum();

        return [
            Stat::make(__('filament-jobs-monitor::translations.total_jobs'), $aggregatedInfo->count ?? 0),
            Stat::make(__('filament-jobs-monitor::translations.pending_jobs'), $queueSize),
            Stat::make(__('filament-jobs-monitor::translations.execution_time'), ($aggregatedInfo->total_time_elapsed ?? 0) . 's'),
            Stat::make(__('filament-jobs-monitor::translations.average_time'), ceil((float) $aggregatedInfo->average_time_elapsed) . 's' ?? 0),
        ];
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
        if (DB::connection()->getConfig('driver') === 'pgsql') {
            return sprintf('CAST(EXTRACT(EPOCH FROM %s) AS INTEGER)', $colName);
        }

        return $colName;
    }
}
