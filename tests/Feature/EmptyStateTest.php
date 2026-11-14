<?php

use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

/**
 * Without its own empty state, the Job History table fell back to Filament's
 * "No :model" heading built from the plural label, which reads badly in
 * languages that inflect ("Žiadny Frontové úlohy" in Slovak).
 */
class EmptyStateTableHost extends Component implements HasTable
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

it('gives the job history table its own empty state', function (string $locale) {
    app()->setLocale($locale);

    $livewire = new EmptyStateTableHost;
    $table = $livewire->table(Table::make($livewire));

    expect($table->getEmptyStateHeading())->toBe(__('filament-jobs-monitor::translations.no_jobs'))
        ->and($table->getEmptyStateDescription())->toBe(__('filament-jobs-monitor::translations.no_jobs_description'));
})->with(['en', 'sk']);

it('translates the job history empty state in Slovak', function () {
    app()->setLocale('sk');

    $livewire = new EmptyStateTableHost;

    expect($livewire->table(Table::make($livewire))->getEmptyStateHeading())->toBe('Žiadne úlohy');
});
