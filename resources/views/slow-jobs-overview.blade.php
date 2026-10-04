@php
    use Carbon\CarbonInterval;

    $rows = $this->getSlowest();
    $format = fn (float $seconds): string => CarbonInterval::seconds((int) round($seconds))->cascade()->forHumans(short: true, parts: 2);
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('filament-jobs-monitor::translations.top_slow_jobs')"
        :description="__('filament-jobs-monitor::translations.top_slow_jobs_description')"
        collapsible
    >
        @if (count($rows))
            <div class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                <div class="flex items-center gap-3 py-2 text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <span class="grow">{{ __('filament-jobs-monitor::translations.name') }}</span>
                    <span class="w-20 text-right">{{ __('filament-jobs-monitor::translations.runs') }}</span>
                    <span class="w-24 text-right">{{ __('filament-jobs-monitor::translations.median') }}</span>
                    <span class="w-24 text-right">{{ __('filament-jobs-monitor::translations.p95') }}</span>
                    <span class="w-24 text-right">{{ __('filament-jobs-monitor::translations.trend') }}</span>
                </div>

                @foreach ($rows as $row)
                    <div class="flex items-center gap-3 py-2">
                        <span class="grow truncate font-mono text-xs text-gray-950 dark:text-white" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        <span class="w-20 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $row['runs'] }}</span>
                        <span class="w-24 text-right tabular-nums font-medium text-gray-950 dark:text-white">{{ $format($row['median']) }}</span>
                        <span class="w-24 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $format($row['p95']) }}</span>
                        <span @class([
                            'w-24 text-right tabular-nums',
                            'text-gray-400 dark:text-gray-500' => $row['trend'] === null,
                            'text-danger-600 dark:text-danger-400' => $row['trend'] !== null && $row['trend'] > 0.1,
                            'text-success-600 dark:text-success-400' => $row['trend'] !== null && $row['trend'] < -0.1,
                        ])>
                            @if ($row['trend'] === null)
                                —
                            @else
                                {{ $row['trend'] > 0 ? '↑' : ($row['trend'] < 0 ? '↓' : '→') }} {{ number_format(abs($row['trend']) * 100) }}%
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament-jobs-monitor::translations.no_slow_jobs') }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
