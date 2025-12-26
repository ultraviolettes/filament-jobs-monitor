<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages;

use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\QueueStatsOverview;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListQueueMonitors extends ListRecords
{
    public static string $resource = QueueMonitorResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHeaderWidgets(): array
    {
        return [
            QueueStatsOverview::class,
        ];
    }

    public function getTitle(): string
    {
        return __('filament-jobs-monitor::translations.title');
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('filament-jobs-monitor::translations.all_jobs'))
                ->icon('heroicon-o-queue-list')
                ->badge(QueueMonitor::count()),
            'running' => Tab::make(__('filament-jobs-monitor::translations.running'))
                ->icon('heroicon-o-arrow-path')
                ->badge(QueueMonitor::whereNull('finished_at')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('finished_at')),
            'succeeded' => Tab::make(__('filament-jobs-monitor::translations.succeeded'))
                ->icon('heroicon-o-check-circle')
                ->badge(QueueMonitor::whereNotNull('finished_at')->where('failed', false)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('finished_at')->where('failed', false)),
            'failed' => Tab::make(__('filament-jobs-monitor::translations.failed'))
                ->icon('heroicon-o-x-circle')
                ->badge(QueueMonitor::whereNotNull('finished_at')->where('failed', true)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('finished_at')->where('failed', true)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
