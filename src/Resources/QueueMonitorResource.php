<?php

namespace Croustibat\FilamentJobsMonitor\Resources;

use Croustibat\FilamentJobsMonitor\Columns\ProgressColumn;
use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Croustibat\FilamentJobsMonitor\Jobs\RetryFailedJobJob;
use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListPendingJobs;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListQueueMonitors;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\QueueStatsOverview;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Resources\Resource\Concerns\HasNavigation;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class QueueMonitorResource extends Resource
{
    use HasNavigation;

    protected static ?string $model = QueueMonitor::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('job_id')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->maxLength(255),
                TextInput::make('queue')
                    ->maxLength(255),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
                Toggle::make('failed')
                    ->required(),
                TextInput::make('attempt')
                    ->required(),
                Textarea::make('exception_message')
                    ->maxLength(65535),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->label(__('filament-jobs-monitor::translations.status'))
                    ->formatStateUsing(fn (string $state): string => __("filament-jobs-monitor::translations.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'running' => 'primary',
                        'succeeded' => 'success',
                        'failed' => 'danger',
                    })
                    ->sortable(false)
                    ->searchable(false),
                TextColumn::make('name')
                    ->label(__('filament-jobs-monitor::translations.name'))
                    ->sortable(),
                TextColumn::make('queue')
                    ->label(__('filament-jobs-monitor::translations.queue'))
                    ->sortable(),
                ProgressColumn::make('progress')
                    ->label(__('filament-jobs-monitor::translations.progress'))
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label(__('filament-jobs-monitor::translations.started_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->actions([
                Action::make('retry')
                    ->label(__('filament-jobs-monitor::translations.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        TextInput::make('delay')
                            ->label(__('filament-jobs-monitor::translations.delay_in_minutes'))
                            ->helperText(__('filament-jobs-monitor::translations.delay_helper'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix(__('filament-jobs-monitor::translations.minutes')),
                    ])
                    ->visible(fn (QueueMonitor $record): bool => $record->hasFailed())
                    ->action(function (QueueMonitor $record, array $data): void {
                        $failedJob = FailedJob::where('uuid', $record->job_id)->first();

                        if (! $failedJob) {
                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.retry_failed'))
                                ->body(__('filament-jobs-monitor::translations.retry_failed_description'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $delay = (int) ($data['delay'] ?? 0);

                        if ($delay > 0) {
                            RetryFailedJobJob::dispatch([$failedJob->uuid])->delay(now()->addMinutes($delay));

                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.retry_scheduled'))
                                ->body(__('filament-jobs-monitor::translations.retry_scheduled_description', ['minutes' => $delay]))
                                ->success()
                                ->send();
                        } else {
                            Artisan::call('queue:retry', ['id' => [$failedJob->uuid]]);

                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.retry_success'))
                                ->body(__('filament-jobs-monitor::translations.retry_success_description'))
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('details')
                    ->label(__('filament-jobs-monitor::translations.details'))
                    ->icon('heroicon-o-information-circle')
                    ->modalContent(fn (QueueMonitor $queueMonitor) => view('filament-jobs-monitor::queue-monitor-details', [
                        'exception_message' => $queueMonitor->exception_message,
                        'failed' => $queueMonitor->failed,
                        'attempts' => $queueMonitor->attempt,
                    ]))
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                BulkAction::make('retry')
                    ->label(__('filament-jobs-monitor::translations.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        TextInput::make('delay')
                            ->label(__('filament-jobs-monitor::translations.delay_in_minutes'))
                            ->helperText(__('filament-jobs-monitor::translations.delay_helper'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix(__('filament-jobs-monitor::translations.minutes')),
                    ])
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data): void {
                        $failedRecords = $records->filter(fn (QueueMonitor $record) => $record->hasFailed());

                        if ($failedRecords->isEmpty()) {
                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.no_failed_jobs'))
                                ->body(__('filament-jobs-monitor::translations.no_failed_jobs_description'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $delay = (int) ($data['delay'] ?? 0);
                        $uuids = [];
                        $failedCount = 0;

                        foreach ($failedRecords as $record) {
                            $failedJob = FailedJob::where('uuid', $record->job_id)->first();

                            if ($failedJob) {
                                $uuids[] = $failedJob->uuid;
                            } else {
                                $failedCount++;
                            }
                        }

                        if (count($uuids) > 0) {
                            if ($delay > 0) {
                                RetryFailedJobJob::dispatch($uuids)->delay(now()->addMinutes($delay));

                                Notification::make()
                                    ->title(__('filament-jobs-monitor::translations.bulk_retry_scheduled'))
                                    ->body(__('filament-jobs-monitor::translations.bulk_retry_scheduled_description', ['count' => count($uuids), 'minutes' => $delay]))
                                    ->success()
                                    ->send();
                            } else {
                                Artisan::call('queue:retry', ['id' => $uuids]);

                                Notification::make()
                                    ->title(__('filament-jobs-monitor::translations.bulk_retry_success'))
                                    ->body(trans_choice('filament-jobs-monitor::translations.bulk_retry_success_description', count($uuids), ['count' => count($uuids)]))
                                    ->success()
                                    ->send();
                            }
                        }

                        if ($failedCount > 0) {
                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.bulk_retry_partial'))
                                ->body(trans_choice('filament-jobs-monitor::translations.bulk_retry_partial_description', $failedCount, ['count' => $failedCount]))
                                ->warning()
                                ->send();
                        }
                    }),
                DeleteBulkAction::make(),
            ])
            ->headerActions([
                Action::make('retry_all_failed')
                    ->label(__('filament-jobs-monitor::translations.retry_all_failed'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        TextInput::make('delay')
                            ->label(__('filament-jobs-monitor::translations.delay_in_minutes'))
                            ->helperText(__('filament-jobs-monitor::translations.delay_helper'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix(__('filament-jobs-monitor::translations.minutes')),
                    ])
                    ->visible(fn (): bool => FailedJob::count() > 0)
                    ->action(function (array $data): void {
                        $failedJobsCount = FailedJob::count();

                        if ($failedJobsCount === 0) {
                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.no_failed_jobs_to_retry'))
                                ->body(__('filament-jobs-monitor::translations.no_failed_jobs_to_retry_description'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $delay = (int) ($data['delay'] ?? 0);

                        if ($delay > 0) {
                            RetryFailedJobJob::dispatch('all')->delay(now()->addMinutes($delay));

                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.retry_all_scheduled'))
                                ->body(__('filament-jobs-monitor::translations.retry_all_scheduled_description', ['count' => $failedJobsCount, 'minutes' => $delay]))
                                ->success()
                                ->send();
                        } else {
                            Artisan::call('queue:retry', ['id' => ['all']]);

                            Notification::make()
                                ->title(__('filament-jobs-monitor::translations.retry_all_success'))
                                ->body(trans_choice('filament-jobs-monitor::translations.retry_all_success_description', $failedJobsCount, ['count' => $failedJobsCount]))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament-jobs-monitor::translations.status'))
                    ->options([
                        'running' => __('filament-jobs-monitor::translations.running'),
                        'succeeded' => __('filament-jobs-monitor::translations.succeeded'),
                        'failed' => __('filament-jobs-monitor::translations.failed'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'succeeded') {
                            return $query
                                ->whereNotNull('finished_at')
                                ->where('failed', 0);
                        } elseif ($data['value'] === 'failed') {
                            return $query
                                ->whereNotNull('finished_at')
                                ->where('failed', 1);
                        } elseif ($data['value'] === 'running') {
                            return $query
                                ->whereNull('finished_at');
                        }
                    }),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return FilamentJobsMonitorPlugin::get()->getNavigationCountBadge() ? number_format(static::getModel()::count()) : null;
    }

    public static function getModelLabel(): string
    {
        return FilamentJobsMonitorPlugin::get()->getLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return FilamentJobsMonitorPlugin::get()->getPluralLabel();
    }

    public static function getNavigationLabel(): string
    {
        return Str::title(static::getPluralModelLabel()) ?? Str::title(static::getModelLabel());
    }

    public static function getCluster(): ?string
    {
        return config('filament-jobs-monitor.resources.cluster');
    }

    public static function getNavigationGroup(): ?string
    {
        return FilamentJobsMonitorPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentJobsMonitorPlugin::get()->getNavigationSort();
    }

    public static function getBreadcrumb(): string
    {
        return FilamentJobsMonitorPlugin::get()->getBreadcrumb();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return FilamentJobsMonitorPlugin::get()->shouldRegisterNavigation();
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        if (filled(config('filament-jobs-monitor.resources.sub_navigation_position'))) {
            return config('filament-jobs-monitor.resources.sub_navigation_position');
        }

        return parent::getSubNavigationPosition();
    }

    public static function getNavigationIcon(): string
    {
        return FilamentJobsMonitorPlugin::get()->getNavigationIcon();
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListQueueMonitors::route('/'),
        ];

        if (QueueJob::isSupported()) {
            $pages['pending'] = ListPendingJobs::route('/pending');
        }

        return $pages;
    }

    public static function getWidgets(): array
    {
        return [
            QueueStatsOverview::class,
        ];
    }
}
