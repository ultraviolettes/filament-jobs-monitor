<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages;

use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\FailureGroup;
use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\FailureStatsOverview;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;

class ListFailures extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = QueueMonitorResource::class;

    protected string $view = 'filament-jobs-monitor::failures';

    public ?string $activeTab = 'open';

    public static function getNavigationLabel(): string
    {
        return __('filament-jobs-monitor::translations.failures');
    }

    public function getTitle(): string
    {
        return __('filament-jobs-monitor::translations.failures');
    }

    public function getSubheading(): ?string
    {
        return __('filament-jobs-monitor::translations.failures_subheading');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return (bool) config('filament-jobs-monitor.failures.enabled', true);
    }

    public function getHeaderWidgets(): array
    {
        return [
            FailureStatsOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_all_failed')
                ->label(__('filament-jobs-monitor::translations.retry_all_failed'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription(__('filament-jobs-monitor::translations.retry_all_failed_confirmation'))
                ->visible(fn (): bool => resolve(FailedJob::class)::count() > 0)
                ->action(function (): void {
                    $count = resolve(FailedJob::class)::count();

                    Artisan::call('queue:retry', ['id' => ['all']]);

                    Notification::make()
                        ->title(__('filament-jobs-monitor::translations.retry_all_success'))
                        ->body(trans_choice('filament-jobs-monitor::translations.retry_all_success_description', $count, ['count' => $count]))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    public function getTabCounts(): array
    {
        return [
            'open' => resolve(FailureGroup::class)::whereNull('resolved_at')->count(),
            'resolved' => resolve(FailureGroup::class)::whereNotNull('resolved_at')->count(),
            'all' => resolve(FailureGroup::class)::count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = resolve(FailureGroup::class)::query();

                return match ($this->activeTab) {
                    'open' => $query->whereNull('resolved_at'),
                    'resolved' => $query->whereNotNull('resolved_at'),
                    default => $query,
                };
            })
            ->columns([
                TextColumn::make('exception_class')
                    ->label(__('filament-jobs-monitor::translations.exception'))
                    ->badge()
                    ->color(fn (FailureGroup $record): string => $record->isResolved() ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->tooltip(fn (FailureGroup $record): string => $record->exception_class),
                TextColumn::make('message')
                    ->label(__('filament-jobs-monitor::translations.message'))
                    ->fontFamily(FontFamily::Mono)
                    ->limit(70)
                    ->tooltip(fn (FailureGroup $record): ?string => $record->message)
                    ->searchable()
                    ->description(fn (FailureGroup $record): ?string => $record->queue
                        ? __('filament-jobs-monitor::translations.queue').': '.$record->queue
                        : null),
                TextColumn::make('job_class')
                    ->label(__('filament-jobs-monitor::translations.job_class'))
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->tooltip(fn (FailureGroup $record): ?string => $record->job_class)
                    ->searchable(),
                TextColumn::make('occurrences_count')
                    ->label(__('filament-jobs-monitor::translations.occurrences'))
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->description(function (FailureGroup $record): ?string {
                        $today = $record->dailyCounts(7)[6] ?? 0;

                        return $today > 0
                            ? __('filament-jobs-monitor::translations.new_today', ['count' => $today])
                            : null;
                    }),
                TextColumn::make('last_occurred_at')
                    ->label(__('filament-jobs-monitor::translations.last_seen'))
                    ->since()
                    ->sortable(),
                ViewColumn::make('trend')
                    ->label(__('filament-jobs-monitor::translations.trend_7d'))
                    ->view('filament-jobs-monitor::columns.sparkline-column'),
            ])
            ->defaultSort('last_occurred_at', 'desc')
            ->filters([
                SelectFilter::make('exception_class')
                    ->label(__('filament-jobs-monitor::translations.exception'))
                    ->options(fn (): array => resolve(FailureGroup::class)::query()
                        ->distinct()
                        ->orderBy('exception_class')
                        ->pluck('exception_class', 'exception_class')
                        ->mapWithKeys(fn (string $class): array => [$class => class_basename($class)])
                        ->all()),
                SelectFilter::make('queue')
                    ->label(__('filament-jobs-monitor::translations.queue'))
                    ->options(fn (): array => resolve(FailureGroup::class)::query()
                        ->whereNotNull('queue')
                        ->distinct()
                        ->orderBy('queue')
                        ->pluck('queue', 'queue')
                        ->all()),
            ])
            ->actions([
                $this->getViewAction(),
                $this->getRetryGroupAction(),
                $this->getResolveAction(),
                $this->getReopenAction(),
            ])
            ->bulkActions([
                BulkAction::make('mark_resolved')
                    ->label(__('filament-jobs-monitor::translations.mark_resolved'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $records->each(fn (FailureGroup $group) => $group->markResolved());

                        Notification::make()
                            ->title(trans_choice('filament-jobs-monitor::translations.bulk_resolved', $records->count(), ['count' => $records->count()]))
                            ->success()
                            ->send();
                    }),
            ])
            ->poll(config('filament-jobs-monitor.failures.polling_interval'))
            ->emptyStateHeading(__('filament-jobs-monitor::translations.no_failures'))
            ->emptyStateDescription(__('filament-jobs-monitor::translations.no_failures_description'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function getViewAction(): Action
    {
        return Action::make('view')
            ->label(__('filament-jobs-monitor::translations.view'))
            ->icon('heroicon-o-eye')
            ->slideOver()
            ->modalWidth(Width::SixExtraLarge)
            ->modalHeading(fn (FailureGroup $record): string => class_basename($record->exception_class))
            ->modalDescription(fn (FailureGroup $record): ?string => $record->message)
            ->modalContent(function (FailureGroup $record) {
                $lastOccurrence = $record->monitors()->latest('started_at')->first();

                $failedJob = $lastOccurrence?->job_id
                    ? resolve(FailedJob::class)::where('uuid', $lastOccurrence->job_id)->first()
                    : null;

                return view('filament-jobs-monitor::failure-details', [
                    'group' => $record,
                    'lastOccurrence' => $lastOccurrence,
                    'payload' => $failedJob?->payload,
                    'recentOccurrences' => $record->monitors()->latest('started_at')->limit(10)->get(),
                ]);
            })
            ->modalSubmitAction(false)
            ->extraModalFooterActions([
                Action::make('mark_resolved_from_details')
                    ->label(__('filament-jobs-monitor::translations.mark_resolved'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FailureGroup $record): bool => ! $record->isResolved())
                    ->action(function (FailureGroup $record): void {
                        $record->markResolved();

                        Notification::make()
                            ->title(__('filament-jobs-monitor::translations.marked_resolved'))
                            ->success()
                            ->send();
                    })
                    ->cancelParentActions(),
                Action::make('retry_group_from_details')
                    ->label(__('filament-jobs-monitor::translations.retry_group'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(fn (FailureGroup $record) => $this->retryGroup($record))
                    ->cancelParentActions(),
            ]);
    }

    protected function getRetryGroupAction(): Action
    {
        return Action::make('retry_group')
            ->label(__('filament-jobs-monitor::translations.retry'))
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription(__('filament-jobs-monitor::translations.retry_group_confirmation'))
            ->action(fn (FailureGroup $record) => $this->retryGroup($record));
    }

    protected function getResolveAction(): Action
    {
        return Action::make('mark_resolved')
            ->label(__('filament-jobs-monitor::translations.mark_resolved'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (FailureGroup $record): bool => ! $record->isResolved())
            ->action(function (FailureGroup $record): void {
                $record->markResolved();

                Notification::make()
                    ->title(__('filament-jobs-monitor::translations.marked_resolved'))
                    ->success()
                    ->send();
            });
    }

    protected function getReopenAction(): Action
    {
        return Action::make('reopen')
            ->label(__('filament-jobs-monitor::translations.reopen'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (FailureGroup $record): bool => $record->isResolved())
            ->action(function (FailureGroup $record): void {
                $record->reopen();

                Notification::make()
                    ->title(__('filament-jobs-monitor::translations.reopened'))
                    ->success()
                    ->send();
            });
    }

    protected function retryGroup(FailureGroup $group): void
    {
        $uuids = $group->monitors()
            ->where('failed', true)
            ->whereNotNull('job_id')
            ->pluck('job_id')
            ->unique();

        $existing = $uuids->isNotEmpty()
            ? resolve(FailedJob::class)::whereIn('uuid', $uuids)->pluck('uuid')
            : collect();

        if ($existing->isEmpty()) {
            Notification::make()
                ->title(__('filament-jobs-monitor::translations.no_failed_jobs_to_retry'))
                ->body(__('filament-jobs-monitor::translations.retry_group_none_found'))
                ->warning()
                ->send();

            return;
        }

        Artisan::call('queue:retry', ['id' => $existing->all()]);

        $missing = $uuids->count() - $existing->count();

        $body = trans_choice('filament-jobs-monitor::translations.retry_group_success_description', $existing->count(), ['count' => $existing->count()]);

        if ($missing > 0) {
            $body .= ' '.trans_choice('filament-jobs-monitor::translations.retry_group_missing_description', $missing, ['count' => $missing]);
        }

        Notification::make()
            ->title(__('filament-jobs-monitor::translations.retry_group_success'))
            ->body($body)
            ->success()
            ->send();
    }

    public function getSubNavigation(): array
    {
        $items = [
            ListQueueMonitors::class,
        ];

        if (resolve(QueueJob::class)::isSupported()) {
            $items[] = ListPendingJobs::class;
        }

        $items[] = ListFailures::class;

        return $this->generateNavigationItems($items);
    }
}
