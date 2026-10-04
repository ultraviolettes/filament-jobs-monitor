@php
    use Illuminate\Support\Str;
@endphp

<div class="space-y-6 text-sm">
    {{-- meta strip --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            [__('filament-jobs-monitor::translations.occurrences'), number_format($group->occurrences_count)],
            [__('filament-jobs-monitor::translations.queue'), $group->queue ?? '—'],
            [__('filament-jobs-monitor::translations.first_seen'), $group->first_occurred_at?->diffForHumans() ?? '—'],
            [__('filament-jobs-monitor::translations.last_seen'), $group->last_occurred_at?->diffForHumans() ?? '—'],
        ] as [$label, $value])
            <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <div class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-0.5 truncate font-mono text-sm font-semibold text-gray-950 dark:text-white" title="{{ $value }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @if ($group->job_class)
        <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
            {{ $group->job_class }}
            @if ($group->isResolved())
                · <span class="font-medium text-success-600 dark:text-success-400">{{ __('filament-jobs-monitor::translations.resolved') }} {{ $group->resolved_at?->diffForHumans() }}</span>
            @endif
        </div>
    @endif

    {{-- stack trace --}}
    @include('filament-jobs-monitor::partials.stack-trace', [
        'rawTrace' => $lastOccurrence?->exception,
        'exceptionClass' => $group->exception_class,
        'emptyMessage' => $lastOccurrence?->exception_message,
    ])

    {{-- payload --}}
    <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-200 px-3 py-2 dark:border-white/10">
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('filament-jobs-monitor::translations.job_payload') }}</span>
        </div>
        <div class="max-h-96 overflow-auto px-3 py-2.5 font-mono text-xs leading-relaxed">
            @if (is_array($payload) && count($payload))
                @include('filament-jobs-monitor::partials.json-tree', ['data' => $payload, 'depth' => 0])
            @else
                <span class="italic text-gray-400 dark:text-gray-500">{{ __('filament-jobs-monitor::translations.no_payload') }}</span>
            @endif
        </div>
    </div>

    {{-- recent occurrences --}}
    <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-200 px-3 py-2 dark:border-white/10">
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('filament-jobs-monitor::translations.recent_occurrences') }}</span>
        </div>
        @if ($recentOccurrences->isNotEmpty())
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($recentOccurrences as $occurrence)
                    <div class="flex items-center gap-3 px-3 py-2 font-mono text-xs">
                        <span class="truncate text-gray-500 dark:text-gray-400" title="{{ $occurrence->job_id }}">{{ Str::limit($occurrence->job_id, 12, '…') }}</span>
                        <span class="text-gray-400 dark:text-gray-500">{{ __('filament-jobs-monitor::translations.attempts') }} {{ $occurrence->attempt }}</span>
                        @if ($occurrence->queue)
                            <span class="text-gray-400 dark:text-gray-500">{{ $occurrence->queue }}</span>
                        @endif
                        <span class="ms-auto whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $occurrence->started_at?->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="px-3 py-3 text-xs italic text-gray-400 dark:text-gray-500">—</div>
        @endif
    </div>
</div>
