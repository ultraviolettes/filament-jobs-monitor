<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages;

use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\QueueStatsOverview;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\PageRegistration;

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

    public static function getNavigationLabel(): string
    {
        return __('filament-jobs-monitor::translations.queued_jobs');
    }

    /**
     * @return array<PageRegistration>
     */
    public function getSubNavigation(): array
    {
        $items = [
            ListQueueMonitors::class,
        ];

        if (QueueJob::isSupported()) {
            $items[] = ListPendingJobs::class;
        }

        return $this->generateNavigationItems($items);
    }
}
