<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets;

use Carbon\CarbonInterval;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Number;

class QueueStatsOverview extends BaseWidget
{
    protected function getCards(): array
    {
        $aggregationColumns = [
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(finished_at - started_at) as total_time_elapsed'),
            DB::raw('AVG(finished_at - started_at) as average_time_elapsed'),
        ];

        $aggregatedInfo = QueueMonitor::query()
            ->select($aggregationColumns)
            ->first();

        $queueSize = collect(config('filament-jobs-monitor.queues') ?? ['default'])
            ->map(fn (string $queue): int => Queue::size($queue))
            ->sum();

        $totalJobs = Number::format($aggregatedInfo->count ?? 0);

        $executionTime = CarbonInterval::seconds($aggregatedInfo->total_time_elapsed ?? 0)->cascade()->forHumans(short: true, parts: 3);

        return [
            Stat::make(__('filament-jobs-monitor::translations.total_jobs'), $totalJobs),
            Stat::make(__('filament-jobs-monitor::translations.pending_jobs'), $queueSize),
            Stat::make(__('filament-jobs-monitor::translations.execution_time'), $executionTime),
            Stat::make(__('filament-jobs-monitor::translations.average_time'), ceil((float) $aggregatedInfo->average_time_elapsed).'s' ?? 0),
        ];
    }
}
