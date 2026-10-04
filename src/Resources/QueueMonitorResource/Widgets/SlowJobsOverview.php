<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets;

use Croustibat\FilamentJobsMonitor\SlowJobs;
use Filament\Widgets\Widget;

class SlowJobsOverview extends Widget
{
    /**
     * @var view-string
     *
     * Larastan resolves `view-string` against the views of the application it
     * boots, which does not register this package's `filament-jobs-monitor::`
     * namespace — hence the ignore. PluginStylesheetTest-style coverage lives in
     * SlowJobsTest, which renders the widget for real.
     *
     * @phpstan-ignore property.defaultValue
     */
    protected string $view = 'filament-jobs-monitor::slow-jobs-overview';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SlowJobs::enabled();
    }

    /**
     * @return array<int, array{name: string, runs: int, median: float, p95: float, previous_median: float|null, trend: float|null}>
     */
    public function getSlowest(): array
    {
        return SlowJobs::slowest((int) config('filament-jobs-monitor.slow.widget_limit', 5));
    }
}
