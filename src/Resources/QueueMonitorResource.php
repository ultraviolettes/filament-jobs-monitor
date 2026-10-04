<?php

namespace Croustibat\FilamentJobsMonitor\Resources;

use Carbon\CarbonInterface;
use Croustibat\FilamentJobsMonitor\Authorization;
use Croustibat\FilamentJobsMonitor\Chains;
use Croustibat\FilamentJobsMonitor\Columns\ProgressColumn;
use Croustibat\FilamentJobsMonitor\FilamentJobsMonitorPlugin;
use Croustibat\FilamentJobsMonitor\Jobs\RetryFailedJobJob;
use Croustibat\FilamentJobsMonitor\Models\FailedJob;
use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Models\QueueMonitor;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListBatches;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListFailures;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListPendingJobs;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages\ListQueueMonitors;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\FailureStatsOverview;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Widgets\QueueStatsOverview;
use Croustibat\FilamentJobsMonitor\SlowJobs;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Resources\Resource\Concerns\HasNavigation;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use UnitEnum;

class QueueMonitorResource extends Resource
{
    use HasNavigation;

    protected static ?string $model = QueueMonitor::class;

    public static function getModel(): string
    {
        return resolve(QueueMonitor::class)::class;
    }

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
                        default => 'gray',
                    })
                    ->sortable(false)
                    ->searchable(false),
                TextColumn::make('name')
                    ->label(__('filament-jobs-monitor::translations.name'))
                    ->searchable()
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
                TextColumn::make('duration')
                    ->label(__('filament-jobs-monitor::translations.duration'))
                    ->state(fn (QueueMonitor $record): ?string => static::durationFor($record))
                    ->placeholder('—')
                    ->badge(fn (QueueMonitor $record): bool => SlowJobs::isSlow($record))
                    ->color(fn (QueueMonitor $record): string => SlowJobs::isSlow($record) ? 'warning' : 'gray')
                    ->icon(fn (QueueMonitor $record): ?string => SlowJobs::isSlow($record) ? 'heroicon-m-exclamation-triangle' : null)
                    ->tooltip(fn (QueueMonitor $record): ?string => static::slowTooltipFor($record))
                    // Sorted in SQL, on epoch seconds, so running jobs (no
                    // finished_at) do not have to be hydrated to be ordered.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        resolve(QueueMonitor::class)::elapsedSeconds().' '.($direction === 'desc' ? 'desc' : 'asc')
                    )),
                TextColumn::make('batch_id')
                    ->label(__('filament-jobs-monitor::translations.batch'))
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : Str::limit($state, 8, ''))
                    ->url(fn (QueueMonitor $record): ?string => $record->batch_id !== null && ListBatches::isEnabled()
                        ? ListBatches::getUrl()
                        : null)
                    ->placeholder('—')
                    ->visible(fn (): bool => static::tracksBatches())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attempt')
                    ->label(__('filament-jobs-monitor::translations.attempts'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('finished_at')
                    ->label(__('filament-jobs-monitor::translations.finished_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->visible(fn ($record): bool => $record->hasFailed() && Authorization::allows(Authorization::RETRY, $record))
                    ->action(fn ($record, array $data) => static::retry($record, (int) ($data['delay'] ?? 0))),
                Action::make('details')
                    ->label(__('filament-jobs-monitor::translations.details'))
                    ->icon('heroicon-o-information-circle')
                    ->modalHeading(fn ($record): string => $record->name ?: __('filament-jobs-monitor::translations.details'))
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalContent(fn ($record) => view('filament-jobs-monitor::queue-monitor-details', [
                        'record' => $record,
                        'payload' => static::payloadFor($record),
                        'failuresUrl' => static::failuresUrlFor($record),
                        'timeline' => Chains::timelineFor($record),
                        'chainUrl' => static::chainUrlFor($record),
                    ]))
                    ->extraModalFooterActions([
                        Action::make('retry_from_details')
                            ->label(__('filament-jobs-monitor::translations.retry'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('warning')
                            ->visible(fn ($record): bool => $record->hasFailed() && Authorization::allows(Authorization::RETRY, $record))
                            ->action(fn ($record) => static::retry($record, 0)),
                    ])
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                BulkAction::make('retry')
                    ->visible(fn (): bool => Authorization::allows(Authorization::RETRY))
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
                        $failedRecords = $records->filter(fn (QueueMonitor $record): bool => $record->hasFailed());

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

                        /** @var QueueMonitor $record */
                        foreach ($failedRecords as $record) {
                            $failedJob = resolve(FailedJob::class)::where('uuid', $record->job_id)->first();

                            if ($failedJob) {
                                $uuids[] = $failedJob->uuid;
                            } else {
                                $failedCount++;
                            }
                        }

                        if (count($uuids) > 0) {
                            if ($delay > 0) {
                                resolve(RetryFailedJobJob::class)::dispatch($uuids)->delay(now()->addMinutes($delay));

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
                DeleteBulkAction::make()
                    ->visible(fn (): bool => Authorization::allows(Authorization::DELETE)),
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
                    ->visible(fn (): bool => resolve(FailedJob::class)::count() > 0 && Authorization::allows(Authorization::RETRY))
                    ->action(function (array $data): void {
                        $failedJobsCount = resolve(FailedJob::class)::count();

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
                            resolve(RetryFailedJobJob::class)::dispatch('all')->delay(now()->addMinutes($delay));

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

                Action::make('clearLogs')
                    ->visible(fn (): bool => Authorization::allows(Authorization::CLEAR_LOGS))
                    ->label(__('filament-jobs-monitor::translations.clear_logs'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament-jobs-monitor::translations.clear_logs_heading'))
                    ->modalDescription(__('filament-jobs-monitor::translations.clear_logs_description'))
                    ->modalSubmitActionLabel(__('filament-jobs-monitor::translations.clear_logs_confirm'))
                    ->action(function () {
                        resolve(QueueMonitor::class)::truncate();

                        Notification::make()
                            ->title(__('filament-jobs-monitor::translations.logs_cleared'))
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('queue')
                    ->label(__('filament-jobs-monitor::translations.queue'))
                    ->options(fn (): array => resolve(QueueMonitor::class)::distinctQueues()),
                SelectFilter::make('batch_id')
                    ->label(__('filament-jobs-monitor::translations.batch'))
                    ->visible(fn (): bool => static::tracksBatches())
                    ->searchable()
                    ->options(fn (): array => static::batchFilterOptions()),
                SelectFilter::make('chain_id')
                    ->label(__('filament-jobs-monitor::translations.chain'))
                    ->visible(fn (): bool => static::tracksBatches())
                    ->searchable()
                    ->options(fn (): array => static::chainFilterOptions()),
                Filter::make('started_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('filament-jobs-monitor::translations.from')),
                        DatePicker::make('until')
                            ->label(__('filament-jobs-monitor::translations.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('started_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('started_at', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = __('filament-jobs-monitor::translations.from').': '.$data['from'];
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = __('filament-jobs-monitor::translations.until').': '.$data['until'];
                        }

                        return $indicators;
                    }),
                SelectFilter::make('status')
                    ->label(__('filament-jobs-monitor::translations.status'))
                    ->options([
                        'running' => __('filament-jobs-monitor::translations.running'),
                        'succeeded' => __('filament-jobs-monitor::translations.succeeded'),
                        'failed' => __('filament-jobs-monitor::translations.failed'),
                        'slow' => __('filament-jobs-monitor::translations.slow'),
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
                        } elseif ($data['value'] === 'slow') {
                            // The absolute threshold only: the per-class medians
                            // are computed in PHP and cannot be expressed here.
                            return $query
                                ->whereNotNull('finished_at')
                                ->where('failed', 0)
                                ->whereRaw(resolve(QueueMonitor::class)::elapsedSeconds().' >= ?', [SlowJobs::thresholdSeconds() ?? PHP_INT_MAX]);
                        }
                    }),
            ])
            ->poll(config('filament-jobs-monitor.resources.polling_interval'));
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
        return Str::title(static::getPluralModelLabel());
    }

    /**
     * Whether the batches migration has been run. Memoized: the table renders
     * this per column, not per row.
     */
    protected static ?bool $tracksBatches = null;

    public static function tracksBatches(): bool
    {
        if (static::$tracksBatches !== null) {
            return static::$tracksBatches;
        }

        try {
            static::$tracksBatches = \Illuminate\Support\Facades\Schema::connection(config('filament-jobs-monitor.connection'))
                ->hasColumn(resolve(QueueMonitor::class)->getTable(), 'batch_id');
        } catch (\Throwable) {
            static::$tracksBatches = false;
        }

        return static::$tracksBatches;
    }

    /**
     * The batches jobs were actually monitored in, newest first.
     *
     * @return array<string, string>
     */
    public static function batchFilterOptions(): array
    {
        if (! static::tracksBatches()) {
            return [];
        }

        return resolve(QueueMonitor::class)::query()
            ->whereNotNull('batch_id')
            ->distinct()
            ->orderByDesc('started_at')
            ->limit(50)
            ->pluck('batch_id', 'batch_id')
            ->map(fn (string $id): string => Str::limit($id, 8, ''))
            ->all();
    }

    /**
     * The chains jobs were monitored in, newest first.
     *
     * @return array<string, string>
     */
    public static function chainFilterOptions(): array
    {
        if (! static::tracksBatches()) {
            return [];
        }

        return resolve(QueueMonitor::class)::query()
            ->whereNotNull('chain_id')
            ->distinct()
            ->orderByDesc('started_at')
            ->limit(50)
            ->pluck('chain_id', 'chain_id')
            ->map(fn (string $id): string => Str::limit($id, 8, ''))
            ->all();
    }

    /**
     * Why a run is flagged as slow, for the column tooltip.
     */
    public static function slowTooltipFor(QueueMonitor $record): ?string
    {
        $inspection = SlowJobs::inspect($record);

        if (! $inspection['slow']) {
            return null;
        }

        if ($inspection['reason'] === 'anomaly') {
            return __('filament-jobs-monitor::translations.slower_than_usual', [
                'ratio' => $inspection['ratio'],
                'median' => round((float) $inspection['median'], 1),
            ]);
        }

        return __('filament-jobs-monitor::translations.over_slow_threshold', [
            'seconds' => SlowJobs::thresholdSeconds(),
        ]);
    }

    /**
     * How long a job ran, human readable, or null while it is still running.
     */
    public static function durationFor(QueueMonitor $record): ?string
    {
        if (! $record->started_at || ! $record->finished_at) {
            return null;
        }

        return $record->started_at->diffForHumans($record->finished_at, [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'short' => true,
            'parts' => 2,
        ]);
    }

    /**
     * Retry a failed job, optionally after a delay in minutes.
     */
    public static function retry(QueueMonitor $record, int $delay = 0): void
    {
        $failedJob = resolve(FailedJob::class)::where('uuid', $record->job_id)->first();

        if (! $failedJob) {
            Notification::make()
                ->title(__('filament-jobs-monitor::translations.retry_failed'))
                ->body(__('filament-jobs-monitor::translations.retry_failed_description'))
                ->danger()
                ->send();

            return;
        }

        if ($delay > 0) {
            RetryFailedJobJob::dispatch([$failedJob->uuid])->delay(now()->addMinutes($delay));

            Notification::make()
                ->title(__('filament-jobs-monitor::translations.retry_scheduled'))
                ->body(__('filament-jobs-monitor::translations.retry_scheduled_description', ['minutes' => $delay]))
                ->success()
                ->send();

            return;
        }

        Artisan::call('queue:retry', ['id' => [$failedJob->uuid]]);

        Notification::make()
            ->title(__('filament-jobs-monitor::translations.retry_success'))
            ->body(__('filament-jobs-monitor::translations.retry_success_description'))
            ->success()
            ->send();
    }

    /**
     * The payload of a monitored job, when Laravel still has it.
     *
     * Only a failed job keeps one: `failed_jobs` stores the payload, while a job
     * that ran to completion is deleted from the queue with its payload.
     *
     * @return array<string, mixed>|null
     */
    public static function payloadFor(QueueMonitor $record): ?array
    {
        if (! $record->hasFailed()) {
            return null;
        }

        $payload = resolve(FailedJob::class)::where('uuid', $record->job_id)->first()?->payload;

        return is_array($payload) ? $payload : null;
    }

    /**
     * The Jobs table, filtered on the chain of this job.
     */
    public static function chainUrlFor(QueueMonitor $record): ?string
    {
        if (blank($record->chain_id)) {
            return null;
        }

        return ListQueueMonitors::getUrl(['tableFilters' => ['chain_id' => ['value' => $record->chain_id]]]);
    }

    /**
     * A link to the Failures page for a grouped failure, when it is reachable.
     */
    public static function failuresUrlFor(QueueMonitor $record): ?string
    {
        if (blank($record->failure_signature) || ! ListFailures::canAccess()) {
            return null;
        }

        return ListFailures::getUrl();
    }

    public static function canViewAny(): bool
    {
        return Authorization::allows(Authorization::VIEW_ANY);
    }

    public static function canDelete(Model $record): bool
    {
        return Authorization::allows(Authorization::DELETE, $record);
    }

    public static function canDeleteAny(): bool
    {
        return Authorization::allows(Authorization::DELETE);
    }

    public static function getCluster(): ?string
    {
        return config('filament-jobs-monitor.resources.cluster');
    }

    public static function getNavigationGroup(): null|string|UnitEnum
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
        return FilamentJobsMonitorPlugin::get()->getSubNavigationPosition();
    }

    public static function getNavigationIcon(): string
    {
        return FilamentJobsMonitorPlugin::get()->getNavigationIcon();
    }

    /**
     * The pages of the sub-navigation, in order, for the ones that are enabled.
     *
     * @return array<int, class-string>
     */
    public static function subNavigationPages(): array
    {
        $pages = [ListQueueMonitors::class];

        if (resolve(QueueJob::class)::isSupported()) {
            $pages[] = ListPendingJobs::class;
        }

        if (config('filament-jobs-monitor.failures.enabled', true)) {
            $pages[] = ListFailures::class;
        }

        if (ListBatches::isEnabled()) {
            $pages[] = ListBatches::class;
        }

        return $pages;
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => ListQueueMonitors::route('/'),
        ];

        if (resolve(QueueJob::class)::isSupported()) {
            $pages['pending'] = ListPendingJobs::route('/pending');
        }

        if (config('filament-jobs-monitor.failures.enabled', true)) {
            $pages['failures'] = ListFailures::route('/failures');
        }

        if (ListBatches::isEnabled()) {
            $pages['batches'] = ListBatches::route('/batches');
        }

        return $pages;
    }

    public static function getWidgets(): array
    {
        return [
            QueueStatsOverview::class,
            FailureStatsOverview::class,
        ];
    }
}
