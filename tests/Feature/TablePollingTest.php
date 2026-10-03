<?php

use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListPendingJobs;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

/**
 * The Job History and Pending jobs tables refresh on their own when
 * `resources.polling_interval` is set, so a long-running job's progress can be
 * watched without reloading the page. Polling is opt-in: a refresh of the Job
 * History table also re-runs the four tab count badges. See issue #163.
 */
class PollingTableHost extends Component implements HasTable
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

function pollingIntervalOf(HasTable $livewire): ?string
{
    return $livewire->table(Table::make($livewire))->getPollingInterval();
}

it('does not poll the job tables by default', function () {
    expect(config('filament-jobs-monitor.resources.polling_interval'))->toBeNull()
        ->and(pollingIntervalOf(new PollingTableHost))->toBeNull()
        ->and(pollingIntervalOf(new ListPendingJobs))->toBeNull();
});

it('polls the job tables at the configured interval', function (string $interval) {
    config()->set('filament-jobs-monitor.resources.polling_interval', $interval);

    expect(pollingIntervalOf(new PollingTableHost))->toBe($interval)
        ->and(pollingIntervalOf(new ListPendingJobs))->toBe($interval);
})->with(['5s', '10s', '1m']);

it('keeps the failures table on its own polling interval', function () {
    config()->set('filament-jobs-monitor.resources.polling_interval', '30s');
    config()->set('filament-jobs-monitor.failures.polling_interval', '10s');

    expect(pollingIntervalOf(new PollingTableHost))->toBe('30s')
        ->and(config('filament-jobs-monitor.failures.polling_interval'))->toBe('10s');
});
