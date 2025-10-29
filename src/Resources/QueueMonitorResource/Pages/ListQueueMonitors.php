<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages;

use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\QueueStatsOverview;
use Filament\Resources\Pages\ListRecords;

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
}
