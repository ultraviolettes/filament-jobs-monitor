@php
    /**
     * Renders a string-cast exception as a collapsible stack trace.
     *
     * Expects `$rawTrace` (the `exception` column), `$exceptionClass` and
     * `$emptyMessage`, shown when there is no trace to parse.
     */
    $frames = [];
    $throwFrame = null;

    if (filled($rawTrace)) {
        // String-cast exceptions start with "Class: message in /path/file.php:42"
        // before the "Stack trace:" block — surface that as the throw location.
        $parts = preg_split('/\r?\nStack trace:\r?\n/', $rawTrace, 2);

        if (count($parts) === 2 && preg_match('/ in (.+?):(\d+)\s*$/', trim($parts[0]), $m)) {
            $throwFrame = [
                'file' => $m[1],
                'line' => (int) $m[2],
                'vendor' => str_contains($m[1], '/vendor/'),
            ];
        }

        foreach (preg_split('/\r?\n/', $rawTrace) as $line) {
            if (preg_match('/^#(\d+)\s+(.*?)\((\d+)\): (.*)$/', $line, $m)) {
                $frames[] = [
                    'index' => (int) $m[1],
                    'file' => $m[2],
                    'line' => (int) $m[3],
                    'call' => $m[4],
                    'vendor' => str_contains($m[2], '/vendor/'),
                ];
            } elseif (preg_match('/^#(\d+)\s+(.*)$/', $line, $m)) {
                $frames[] = [
                    'index' => (int) $m[1],
                    'file' => null,
                    'line' => null,
                    'call' => $m[2],
                    'vendor' => true,
                ];
            }
        }
    }

    $vendorFramesCount = count(array_filter($frames, fn (array $frame): bool => $frame['vendor']));
@endphp

<div x-data="{ mode: 'app' }" class="overflow-hidden rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
    <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-3 py-2 dark:border-white/10">
        <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('filament-jobs-monitor::translations.stack_trace') }}</span>
        @if (count($frames))
            <span class="text-xs text-gray-400 dark:text-gray-500">{{ trans_choice('filament-jobs-monitor::translations.frames_count', count($frames), ['count' => count($frames)]) }}</span>
        @endif
        <span class="ms-auto flex gap-1.5">
            @foreach (['app' => __('filament-jobs-monitor::translations.app_frames'), 'all' => __('filament-jobs-monitor::translations.all_frames'), 'raw' => __('filament-jobs-monitor::translations.raw')] as $value => $label)
                <button
                    type="button"
                    x-on:click="mode = '{{ $value }}'"
                    x-bind:class="mode === '{{ $value }}' ? 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' : 'text-gray-500 dark:text-gray-400'"
                    class="rounded px-2 py-0.5 text-xs font-medium"
                >{{ $label }}</button>
            @endforeach
        </span>
    </div>

    @if (count($frames) || $throwFrame)
        <div x-show="mode !== 'raw'" class="divide-y divide-gray-100 font-mono text-xs dark:divide-white/5">
            @if ($throwFrame)
                <div class="flex items-center gap-2 px-3 py-1.5 bg-danger-50 dark:bg-danger-950">
                    <span class="font-semibold text-danger-600 dark:text-danger-400">!</span>
                    <span class="truncate font-medium text-gray-950 dark:text-white">{{ class_basename($exceptionClass) }}</span>
                    <span class="ms-auto truncate text-gray-500 dark:text-gray-400" title="{{ $throwFrame['file'] }}:{{ $throwFrame['line'] }}">
                        {{ $throwFrame['file'] }}<span class="text-gray-300 dark:text-gray-600">:</span><span class="text-danger-600 dark:text-danger-400">{{ $throwFrame['line'] }}</span>
                    </span>
                </div>
            @endif

            @foreach ($frames as $frame)
                <div
                    @if ($frame['vendor']) x-show="mode === 'all'" @endif
                    @class([
                        'flex items-center gap-2 px-3 py-1.5',
                        'bg-danger-50 dark:bg-danger-950' => ! $frame['vendor'] && $loop->first && ! $throwFrame,
                    ])
                >
                    <span class="tabular-nums text-gray-400 dark:text-gray-500">#{{ $frame['index'] }}</span>
                    <span @class([
                        'truncate',
                        'font-medium text-gray-950 dark:text-white' => ! $frame['vendor'],
                        'text-gray-500 dark:text-gray-400' => $frame['vendor'],
                    ])>{{ $frame['call'] }}</span>
                    @if ($frame['file'])
                        <span class="ms-auto truncate text-gray-400 dark:text-gray-500" title="{{ $frame['file'] }}:{{ $frame['line'] }}">
                            {{ $frame['file'] }}<span class="text-gray-300 dark:text-gray-600">:</span><span class="text-danger-600 dark:text-danger-400">{{ $frame['line'] }}</span>
                        </span>
                    @endif
                </div>
            @endforeach

            @if ($vendorFramesCount > 0)
                <div x-show="mode === 'app'" class="px-3 py-2 text-gray-400 dark:text-gray-500">
                    {{ trans_choice('filament-jobs-monitor::translations.vendor_frames_hidden', $vendorFramesCount, ['count' => $vendorFramesCount]) }}
                </div>
            @endif
        </div>

        <pre x-show="mode === 'raw'" x-cloak class="max-h-96 overflow-auto px-3 py-2 font-mono text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ $rawTrace }}</pre>
    @else
        <div class="px-3 py-3 text-xs text-gray-500 dark:text-gray-400">
            {{ $emptyMessage ?? __('filament-jobs-monitor::translations.no_stack_trace') }}
        </div>
    @endif
</div>
