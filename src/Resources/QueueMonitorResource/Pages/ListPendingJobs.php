<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages;

use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ListPendingJobs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = QueueMonitorResource::class;

    protected string $view = 'filament-jobs-monitor::pending-jobs';

    public static function getNavigationLabel(): string
    {
        return __('filament-jobs-monitor::translations.pending_jobs');
    }

    public function getTitle(): string
    {
        return __('filament-jobs-monitor::translations.pending_jobs');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(QueueJob::query())
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-jobs-monitor::translations.status'))
                    ->formatStateUsing(fn (string $state): string => __("filament-jobs-monitor::translations.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'processing' => 'primary',
                        'pending' => 'warning',
                        'delayed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(false)
                    ->searchable(false),
                TextColumn::make('name')
                    ->label(__('filament-jobs-monitor::translations.name'))
                    ->sortable(false)
                    ->searchable(false),
                TextColumn::make('queue')
                    ->label(__('filament-jobs-monitor::translations.queue'))
                    ->sortable(),
                TextColumn::make('attempts')
                    ->label(__('filament-jobs-monitor::translations.attempts'))
                    ->sortable(),
                TextColumn::make('available_at')
                    ->label(__('filament-jobs-monitor::translations.available_at'))
                    ->since()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-jobs-monitor::translations.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                DeleteAction::make()
                    ->label(__('filament-jobs-monitor::translations.delete'))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading(__('filament-jobs-monitor::translations.no_pending_jobs'))
            ->emptyStateDescription(__('filament-jobs-monitor::translations.no_pending_jobs_description'));
    }

    public static function canAccess(array $parameters = []): bool
    {
        return QueueJob::isSupported();
    }

    public function getSubNavigation(): array
    {
        $items = [
            ListQueueMonitors::class,
            ListPendingJobs::class,
        ];

        return $this->generateNavigationItems($items);
    }
}
