<x-filament-panels::page>
    @if(\Croustibat\FilamentJobsMonitor\Models\QueueJob::isSupported())
        {{ $this->table }}
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-jobs-monitor::translations.pending_jobs_not_supported_title') }}
            </x-slot>
            <p>{{ __('filament-jobs-monitor::translations.pending_jobs_not_supported_description') }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
