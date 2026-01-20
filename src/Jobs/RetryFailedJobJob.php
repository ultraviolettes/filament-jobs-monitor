<?php

namespace Croustibat\FilamentJobsMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class RetryFailedJobJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string>|string  $uuids  The UUIDs of failed jobs to retry, or 'all'
     */
    public function __construct(
        public array|string $uuids
    ) {}

    public function handle(): void
    {
        $ids = is_array($this->uuids) ? $this->uuids : [$this->uuids];

        Artisan::call('queue:retry', ['id' => $ids]);
    }
}
