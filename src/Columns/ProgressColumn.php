<?php

namespace Croustibat\FilamentJobsMonitor\Columns;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Tables\Columns\Column;

class ProgressColumn extends Column implements HasEmbeddedView
{
    protected string $view = 'filament-tables::columns.column';

    public function toEmbeddedHtml(): string
    {
        $state = $this->getState();
        $progress = is_numeric($state) ? (int) $state : 0;

        $barColor = match (true) {
            $progress >= 100 => '#22c55e', // green-500
            $progress >= 50 => '#3b82f6',  // blue-500
            $progress > 0 => '#eab308',    // yellow-500
            default => '#d1d5db',          // gray-300
        };

        return <<<HTML
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 100px; background-color: #e5e7eb; border-radius: 9999px; height: 8px; overflow: hidden;">
                    <div style="width: {$progress}%; height: 8px; border-radius: 9999px; background-color: {$barColor};"></div>
                </div>
                <span style="font-size: 12px; color: #6b7280;">{$progress}%</span>
            </div>
        HTML;
    }

    public function hasView(): bool
    {
        return false;
    }
}
