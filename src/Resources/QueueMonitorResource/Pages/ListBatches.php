<?php

namespace Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource\Pages;

use Croustibat\FilamentJobsMonitor\Authorization;
use Croustibat\FilamentJobsMonitor\Models\JobBatch;
use Croustibat\FilamentJobsMonitor\Models\QueueJob;
use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;

class ListBatches extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = QueueMonitorResource::class;

    protected string $view = 'filament-jobs-monitor::batches';

    public static function getNavigationLabel(): string
    {
        return __('filament-jobs-monitor::translations.batches');
    }

    public function getTitle(): string
    {
        return __('filament-jobs-monitor::translations.batches');
    }

    public function getSubheading(): ?string
    {
        return __('filament-jobs-monitor::translations.batches_subheading');
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::isEnabled() && Authorization::allows(Authorization::VIEW_ANY);
    }

    /**
     * The page is opt-in, and only makes sense when Laravel stores batches in
     * the database: it reads `job_batches` rather than duplicating its state.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('filament-jobs-monitor.batches.enabled', false)
            && JobBatch::isSupported();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $query = JobBatch::query();

                $tenant = config('filament-jobs-monitor.tenancy.enabled') && app()->bound('filament')
                    ? Filament::getTenant()
                    : null;

                return $tenant ? $query->forTenant($tenant->getKey()) : $query;
            })
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-jobs-monitor::translations.name'))
                    ->state(fn (JobBatch $record): string => $record->displayName())
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('filament-jobs-monitor::translations.status'))
                    ->badge()
                    ->state(fn (JobBatch $record): string => $record->status())
                    ->formatStateUsing(fn (string $state): string => __("filament-jobs-monitor::translations.batch_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'finished' => 'success',
                        'processing' => 'primary',
                        'failed' => 'danger',
                        'cancelled' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('progress')
                    ->label(__('filament-jobs-monitor::translations.progress'))
                    ->state(fn (JobBatch $record): string => $record->progress().'%')
                    ->description(fn (JobBatch $record): string => $record->processedJobs().' / '.$record->total_jobs),
                TextColumn::make('failed_jobs')
                    ->label(__('filament-jobs-monitor::translations.failed'))
                    ->color(fn (JobBatch $record): string => $record->failed_jobs > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('pending_jobs')
                    ->label(__('filament-jobs-monitor::translations.pending'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-jobs-monitor::translations.started_at'))
                    ->state(fn (JobBatch $record) => $record->startedAt())
                    ->since()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label(__('filament-jobs-monitor::translations.finished_at'))
                    ->state(fn (JobBatch $record) => $record->finishedAt())
                    ->placeholder('—')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament-jobs-monitor::translations.status'))
                    ->options([
                        'pending' => __('filament-jobs-monitor::translations.batch_pending'),
                        'processing' => __('filament-jobs-monitor::translations.batch_processing'),
                        'finished' => __('filament-jobs-monitor::translations.batch_finished'),
                        'failed' => __('filament-jobs-monitor::translations.batch_failed'),
                        'cancelled' => __('filament-jobs-monitor::translations.batch_cancelled'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'pending' => $query->whereNull('finished_at')->whereNull('cancelled_at')->whereColumn('pending_jobs', 'total_jobs'),
                        'processing' => $query->whereNull('finished_at')->whereNull('cancelled_at')->whereColumn('pending_jobs', '<', 'total_jobs'),
                        'finished' => $query->whereNotNull('finished_at')->whereNull('cancelled_at')->where('failed_jobs', 0),
                        'failed' => $query->whereNull('cancelled_at')->where('failed_jobs', '>', 0)->where('pending_jobs', 0),
                        'cancelled' => $query->whereNotNull('cancelled_at'),
                        default => $query,
                    }),
            ])
            ->actions([
                $this->getViewAction(),
                $this->getRetryFailedAction(),
                $this->getCancelAction(),
            ])
            ->headerActions([
                $this->getPruneAction(),
            ])
            ->poll(config('filament-jobs-monitor.batches.polling_interval'))
            ->emptyStateHeading(__('filament-jobs-monitor::translations.no_batches'))
            ->emptyStateDescription(__('filament-jobs-monitor::translations.no_batches_description'))
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    protected function getViewAction(): Action
    {
        return Action::make('view')
            ->label(__('filament-jobs-monitor::translations.view'))
            ->icon('heroicon-o-eye')
            ->slideOver()
            ->modalWidth(Width::FiveExtraLarge)
            ->modalHeading(fn (JobBatch $record): string => $record->displayName())
            ->modalContent(fn (JobBatch $record) => view('filament-jobs-monitor::batch-details', [
                'batch' => $record,
                'monitors' => $record->monitors()->get(),
                'pendingJobsSupported' => resolve(QueueJob::class)::isSupported(),
            ]))
            ->modalSubmitAction(false);
    }

    protected function getRetryFailedAction(): Action
    {
        return Action::make('retry_failed')
            ->label(__('filament-jobs-monitor::translations.retry'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription(__('filament-jobs-monitor::translations.retry_batch_confirmation'))
            ->visible(fn (JobBatch $record): bool => $record->failed_jobs > 0 && Authorization::allows(Authorization::RETRY))
            ->action(function (JobBatch $record): void {
                if ($record->toBatch() === null) {
                    Notification::make()
                        ->title(__('filament-jobs-monitor::translations.batch_missing'))
                        ->danger()
                        ->send();

                    return;
                }

                $failed = $record->failed_jobs;

                // Laravel's Batch object has no retry method: retrying a batch
                // is `queue:retry-batch`, which re-queues every failed job id.
                Artisan::call('queue:retry-batch', ['id' => $record->id]);

                Notification::make()
                    ->title(trans_choice('filament-jobs-monitor::translations.batch_retried', $failed, ['count' => $failed]))
                    ->success()
                    ->send();
            });
    }

    protected function getCancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('filament-jobs-monitor::translations.cancel_batch'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('filament-jobs-monitor::translations.cancel_batch_confirmation'))
            ->visible(fn (JobBatch $record): bool => in_array($record->status(), ['pending', 'processing'], true)
                && Authorization::allows(Authorization::DELETE))
            ->action(function (JobBatch $record): void {
                $batch = $record->toBatch();

                if ($batch === null) {
                    Notification::make()
                        ->title(__('filament-jobs-monitor::translations.batch_missing'))
                        ->danger()
                        ->send();

                    return;
                }

                $batch->cancel();

                Notification::make()
                    ->title(__('filament-jobs-monitor::translations.batch_cancelled_notification'))
                    ->success()
                    ->send();
            });
    }

    protected function getPruneAction(): Action
    {
        return Action::make('prune_batches')
            ->label(__('filament-jobs-monitor::translations.prune_batches'))
            ->icon('heroicon-o-trash')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(fn (): string => __('filament-jobs-monitor::translations.prune_batches_confirmation', [
                'days' => static::pruneRetentionDays(),
            ]))
            ->visible(fn (): bool => Authorization::allows(Authorization::DELETE))
            ->action(function (): void {
                // Laravel's own command, so the pruning rules stay Laravel's.
                Artisan::call('queue:prune-batches', ['--hours' => static::pruneRetentionDays() * 24]);

                Notification::make()
                    ->title(__('filament-jobs-monitor::translations.batches_pruned'))
                    ->success()
                    ->send();
            });
    }

    public static function pruneRetentionDays(): int
    {
        return (int) config('filament-jobs-monitor.batches.retention_days', 7);
    }

    public function getSubNavigation(): array
    {
        return $this->generateNavigationItems(QueueMonitorResource::subNavigationPages());
    }
}
