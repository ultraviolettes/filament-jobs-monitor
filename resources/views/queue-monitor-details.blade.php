@php
    use Illuminate\Support\Str;

    $duration = $record->started_at && $record->finished_at
        ? $record->started_at->diffForHumans($record->finished_at, [
            'syntax' => Carbon\CarbonInterface::DIFF_ABSOLUTE,
            'short' => true,
            'parts' => 2,
        ])
        : null;

    $meta = [
        [__('filament-jobs-monitor::translations.status'), __('filament-jobs-monitor::translations.'.$record->status)],
        [__('filament-jobs-monitor::translations.queue'), $record->queue ?: '—'],
        [__('filament-jobs-monitor::translations.attempts'), (string) $record->attempt],
        [__('filament-jobs-monitor::translations.duration'), $duration ?? '—'],
        [__('filament-jobs-monitor::translations.started_at'), $record->started_at?->diffForHumans() ?? '—'],
        [__('filament-jobs-monitor::translations.finished_at'), $record->finished_at?->diffForHumans() ?? '—'],
        [__('filament-jobs-monitor::translations.job_id'), $record->job_id],
        [__('filament-jobs-monitor::translations.progress'), $record->progress !== null ? $record->progress.'%' : '—'],
    ];
@endphp

<div class="space-y-6 text-sm">
    {{-- meta strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($meta as [$label, $value])
            <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-0.5 truncate font-mono text-sm font-semibold text-gray-950 dark:text-white" title="{{ $value }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @if ($record->name)
        <div class="break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $record->name }}</div>
    @endif

    @if ($timeline['total'] > 0)
        @include('filament-jobs-monitor::partials.chain-timeline', [
            'timeline' => $timeline,
            'current' => $record,
            'chainUrl' => $chainUrl,
        ])
    @endif

    {{-- stack trace, only for a job that actually failed --}}
    @if ($record->hasFailed())
        @include('filament-jobs-monitor::partials.stack-trace', [
            'rawTrace' => $record->exception,
            'exceptionClass' => $record->exception_class,
            'emptyMessage' => $record->exception_message,
        ])

        @if ($failuresUrl)
            <a
                href="{{ $failuresUrl }}"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-primary-600 dark:text-primary-400"
            >{{ __('filament-jobs-monitor::translations.view_failure_group') }}</a>
        @endif
    @endif

    {{-- payload --}}
    <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-200 px-3 py-2 dark:border-white/10">
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('filament-jobs-monitor::translations.job_payload') }}</span>
        </div>
        <div class="max-h-96 overflow-auto px-3 py-2.5 font-mono text-xs leading-relaxed">
            @if (is_array($payload) && count($payload))
                @include('filament-jobs-monitor::partials.json-tree', ['data' => $payload, 'depth' => 0])
            @else
                {{-- Laravel only keeps a payload for jobs still queued or already failed. --}}
                <span class="italic text-gray-400 dark:text-gray-500">{{ __('filament-jobs-monitor::translations.no_payload') }}</span>
            @endif
        </div>
    </div>
</div>
