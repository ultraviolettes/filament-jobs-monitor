<?php

namespace Croustibat\FilamentJobsMonitor\Columns;

use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Filament\Tables\Columns\TextColumn;

/**
 * A date time column, shown relative or absolute and with the tooltip the
 * plugin is configured with (`timestamps()` / `timestampTooltip()`), in the
 * panel timezone and Filament's default date time format.
 */
class DateTimeColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $plugin = FilamentJobsMonitorPlugin::get();

        $plugin->getTimestamps() === FilamentJobsMonitorPlugin::TIMESTAMPS_ABSOLUTE
            ? $this->dateTime()
            : $this->since();

        match ($plugin->getTimestampTooltip()) {
            FilamentJobsMonitorPlugin::TIMESTAMPS_RELATIVE => $this->sinceTooltip(),
            FilamentJobsMonitorPlugin::TIMESTAMPS_ABSOLUTE => $this->dateTimeTooltip(),
            default => null,
        };
    }
}
