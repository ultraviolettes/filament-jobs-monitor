<div class="space-y-6 text-sm">
    {{-- meta strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            [__('filament-jobs-monitor::translations.total'), number_format($batch->total_jobs)],
            [__('filament-jobs-monitor::translations.pending'), number_format($batch->pending_jobs)],
            [__('filament-jobs-monitor::translations.failed'), number_format($batch->failed_jobs)],
            [__('filament-jobs-monitor::translations.progress'), $batch->progress().'%'],
        ] as [$label, $value])
            <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-0.5 truncate font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
        {{ $batch->id }} · {{ __('filament-jobs-monitor::translations.batch_'.$batch->status()) }}
        · {{ $batch->startedAt()->diffForHumans() }}
    </div>

    {{-- the jobs of the batch the plugin monitored --}}
    <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-200 px-3 py-2 dark:border-white/10">
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('filament-jobs-monitor::translations.queued_jobs') }}</span>
        </div>

        @if ($monitors->isNotEmpty())
            <div class="max-h-96 divide-y divide-gray-100 overflow-auto dark:divide-white/5">
                @foreach ($monitors as $monitor)
                    <div class="flex items-center gap-3 px-3 py-2 font-mono text-xs">
                        <span @class([
                            'font-medium',
                            'text-danger-600 dark:text-danger-400' => $monitor->hasFailed(),
                            'text-gray-500 dark:text-gray-400' => ! $monitor->hasFailed(),
                        ])>{{ __('filament-jobs-monitor::translations.'.$monitor->status) }}</span>
                        <span class="truncate text-gray-950 dark:text-white" title="{{ $monitor->name }}">{{ $monitor->name }}</span>
                        <span class="ms-auto whitespace-nowrap text-gray-400 dark:text-gray-500">{{ $monitor->started_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @else
            {{-- The jobs may simply not have started yet, or predate the batches migration. --}}
            <div class="px-3 py-3 text-xs italic text-gray-400 dark:text-gray-500">
                {{ __('filament-jobs-monitor::translations.no_monitored_batch_jobs') }}
            </div>
        @endif
    </div>
</div>
