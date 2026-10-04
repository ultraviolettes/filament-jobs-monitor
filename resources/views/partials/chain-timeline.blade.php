@php
    /**
     * A vertical chain timeline. Expects `$timeline` as returned by
     * Chains::timelineFor(), `$current` (the monitor the modal is about) and
     * `$chainUrl`, which may be null.
     */
    $steps = $timeline['steps'];
    $neverReached = $timeline['neverReached'];
@endphp

<div class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
    <div class="flex items-center gap-2 border-b border-gray-200 px-3 py-2 dark:border-white/10">
        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('filament-jobs-monitor::translations.chain') }}</span>
        <span class="text-xs text-gray-400 dark:text-gray-500">{{ trans_choice('filament-jobs-monitor::translations.chain_steps', $timeline['total'], ['count' => $timeline['total']]) }}</span>
    </div>

    <div class="px-3 py-2">
        @foreach ($steps as $step)
            @php
                $status = $step->status;
                [$symbol, $tone] = match ($status) {
                    'failed' => ['✕', 'text-danger-600 dark:text-danger-400'],
                    'running' => ['◍', 'text-primary-600 dark:text-primary-400'],
                    default => ['✓', 'text-success-600 dark:text-success-400'],
                };
            @endphp

            <div class="flex items-start gap-3 py-1.5">
                <span class="w-4 shrink-0 text-center font-semibold {{ $tone }}">{{ $symbol }}</span>

                <div class="min-w-0 grow">
                    <div class="flex items-baseline gap-2">
                        <span @class([
                            'truncate font-mono text-xs',
                            'font-semibold text-gray-950 dark:text-white' => $step->is($current),
                            'text-gray-600 dark:text-gray-300' => ! $step->is($current),
                        ])>{{ $step->name }}</span>

                        @if ($step->is($current))
                            <span class="whitespace-nowrap text-xs text-gray-400 dark:text-gray-500">{{ __('filament-jobs-monitor::translations.this_job') }}</span>
                        @endif

                        <span class="ms-auto whitespace-nowrap text-xs text-gray-400 dark:text-gray-500">{{ $step->started_at?->diffForHumans() }}</span>
                    </div>

                    @if ($status === 'failed' && filled($step->exception_message))
                        <div class="mt-0.5 break-all font-mono text-xs text-danger-600 dark:text-danger-400">{{ $step->exception_message }}</div>
                    @endif
                </div>
            </div>
        @endforeach

        @foreach ($neverReached as $class)
            <div class="flex items-start gap-3 py-1.5 text-gray-400 dark:text-gray-500">
                <span class="w-4 shrink-0 text-center">○</span>
                <div class="flex min-w-0 grow items-baseline gap-2">
                    <span class="truncate font-mono text-xs">{{ $class }}</span>
                    <span class="ms-auto whitespace-nowrap text-xs italic">{{ __('filament-jobs-monitor::translations.never_reached') }}</span>
                </div>
            </div>
        @endforeach

        @if ($timeline['truncated'] && $chainUrl)
            <a href="{{ $chainUrl }}" class="mt-1 inline-flex text-xs font-medium text-primary-600 dark:text-primary-400">
                {{ __('filament-jobs-monitor::translations.view_all_steps') }}
            </a>
        @endif
    </div>
</div>
