<?php

use Croustibat\FilamentJobsMonitor\Chains;
use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Timestamps are shown relative ("3 minutes ago") or absolute (Filament's
 * default date time format), with an optional relative or absolute tooltip,
 * in the tables and in the job details.
 */
class TimestampsTableHost extends Component implements HasTable
{
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return QueueMonitorResource::table($table);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}

beforeEach(function () {
    $migration = include __DIR__.'/../../database/migrations/create_filament-jobs-monitor_table.php.stub';
    $migration->up();

    $this->travelTo(Carbon::parse('2026-03-14 15:09:26'));
});

const STARTED_RELATIVE = '3 minutes ago';
const STARTED_ABSOLUTE = 'Mar 14, 2026 15:06:26';

function timestampsMonitor(array $attributes = []): QueueMonitor
{
    return QueueMonitor::create(array_merge([
        'job_id' => 'job-1',
        'name' => 'App\\Jobs\\ImportOrders',
        'queue' => 'default',
        'started_at' => now()->subMinutes(3),
        'finished_at' => null,
        'failed' => false,
        'attempt' => 1,
    ], $attributes));
}

/**
 * @return array{value: mixed, tooltip: mixed}
 */
function timestampsColumn(QueueMonitor $record, string $column = 'started_at'): array
{
    $livewire = new TimestampsTableHost;

    // The column reads the table (default format, timezone) through the Livewire component.
    (fn () => $this->table = $this->table(Table::make($this)))->call($livewire);

    /** @var TextColumn $column */
    $column = $livewire->getTable()->getColumn($column)->record($record);

    $state = $column->getState();

    return [
        'value' => $column->formatState($state),
        'tooltip' => $column->getTooltip($state),
    ];
}

function timestampsDetails(QueueMonitor $record): string
{
    return view('filament-jobs-monitor::queue-monitor-details', [
        'record' => $record,
        'payload' => QueueMonitorResource::payloadFor($record),
        'failuresUrl' => QueueMonitorResource::failuresUrlFor($record),
        'timeline' => Chains::timelineFor($record),
        'chainUrl' => null,
    ])->render();
}

it('keeps relative timestamps without a tooltip by default', function () {
    expect(FilamentJobsMonitorPlugin::get()->getTimestamps())->toBe(FilamentJobsMonitorPlugin::TIMESTAMPS_RELATIVE)
        ->and(FilamentJobsMonitorPlugin::get()->getTimestampTooltip())->toBeNull()
        ->and(timestampsColumn(timestampsMonitor()))->toBe(['value' => STARTED_RELATIVE, 'tooltip' => null]);
});

it('shows every combination in the tables and the details', function (string $timestamps, ?string $tooltip, string $value, ?string $tooltipText) {
    FilamentJobsMonitorPlugin::get()->timestamps($timestamps)->timestampTooltip($tooltip);

    $record = timestampsMonitor();

    expect(timestampsColumn($record))->toBe(['value' => $value, 'tooltip' => $tooltipText])
        ->and(QueueMonitorResource::formatDateTime($record->started_at))->toBe($value)
        ->and(QueueMonitorResource::formatDateTimeTooltip($record->started_at))->toBe($tooltipText);
})->with([
    'relative, no tooltip' => ['relative', null, STARTED_RELATIVE, null],
    'relative, relative tooltip' => ['relative', 'relative', STARTED_RELATIVE, STARTED_RELATIVE],
    'relative, absolute tooltip' => ['relative', 'absolute', STARTED_RELATIVE, STARTED_ABSOLUTE],
    'absolute, no tooltip' => ['absolute', null, STARTED_ABSOLUTE, null],
    'absolute, relative tooltip' => ['absolute', 'relative', STARTED_ABSOLUTE, STARTED_RELATIVE],
    'absolute, absolute tooltip' => ['absolute', 'absolute', STARTED_ABSOLUTE, STARTED_ABSOLUTE],
]);

it('reads the config keys', function () {
    config()->set('filament-jobs-monitor.resources.timestamps', 'absolute');
    config()->set('filament-jobs-monitor.resources.timestamp_tooltip', 'relative');

    expect(timestampsColumn(timestampsMonitor()))->toBe(['value' => STARTED_ABSOLUTE, 'tooltip' => STARTED_RELATIVE]);
});

it('lets the plugin methods override the config keys, including no tooltip', function () {
    config()->set('filament-jobs-monitor.resources.timestamps', 'absolute');
    config()->set('filament-jobs-monitor.resources.timestamp_tooltip', 'relative');

    FilamentJobsMonitorPlugin::get()
        ->timestamps(FilamentJobsMonitorPlugin::TIMESTAMPS_RELATIVE)
        ->timestampTooltip(null);

    expect(timestampsColumn(timestampsMonitor()))->toBe(['value' => STARTED_RELATIVE, 'tooltip' => null]);
});

it('falls back to relative and no tooltip for an unknown value', function () {
    config()->set('filament-jobs-monitor.resources.timestamps', 'sometimes');
    config()->set('filament-jobs-monitor.resources.timestamp_tooltip', 'always');

    expect(FilamentJobsMonitorPlugin::get()->getTimestamps())->toBe(FilamentJobsMonitorPlugin::TIMESTAMPS_RELATIVE)
        ->and(FilamentJobsMonitorPlugin::get()->getTimestampTooltip())->toBeNull();
});

it('leaves a missing timestamp empty', function () {
    FilamentJobsMonitorPlugin::get()->timestamps('absolute')->timestampTooltip('absolute');

    $record = timestampsMonitor();

    expect(timestampsColumn($record, 'finished_at'))->toBe(['value' => null, 'tooltip' => null])
        ->and(QueueMonitorResource::formatDateTime($record->finished_at))->toBe('—')
        ->and(QueueMonitorResource::formatDateTimeTooltip($record->finished_at))->toBeNull();
});

it('renders the timestamps and their tooltip in the job details', function () {
    FilamentJobsMonitorPlugin::get()->timestamps('absolute')->timestampTooltip('relative');

    $html = timestampsDetails(timestampsMonitor());

    expect($html)
        ->toContain(STARTED_ABSOLUTE)
        ->toContain('x-tooltip')
        ->toContain(STARTED_RELATIVE);
});

it('renders the job details without a tooltip by default', function () {
    $html = timestampsDetails(timestampsMonitor());

    expect($html)
        ->toContain(STARTED_RELATIVE)
        ->not->toContain('x-tooltip');
});
