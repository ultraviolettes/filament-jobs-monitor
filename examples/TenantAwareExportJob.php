<?php

namespace App\Jobs;

use Croustibat\FilamentJobsMonitor\Traits\QueueProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Example job demonstrating multi-tenancy support.
 *
 * The key requirement for multi-tenancy is the public `tenantId` property.
 * When this property exists, the plugin will automatically:
 * - Associate the job monitor record with the tenant
 * - Filter the job list to show only jobs for the current tenant
 */
class TenantAwareExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, QueueProgress, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  string  $tenantId  Required for multi-tenancy - the ID of the tenant this job belongs to
     * @param  Collection  $data  The data to be exported
     * @param  string  $filename  The output filename
     */
    public function __construct(
        public string $tenantId,
        protected Collection $data,
        protected string $filename
    ) {
        $this->data = $data->pluck('email', 'name');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->setProgress(0);

        // Create a stream to write the CSV data to
        $stream = fopen('php://temp', 'w+');

        $total = $this->data->count();
        $processed = 0;

        // Write each item in the collection to the CSV stream
        foreach ($this->data->toArray() as $key => $item) {
            fputcsv($stream, [$key, $item]);
            $processed++;

            if ($total > 0) {
                $this->setProgress((int) ($processed / $total * 80));
            }
        }

        // Rewind the stream pointer to the beginning
        rewind($stream);

        // Read the contents of the stream into a string
        $csv = stream_get_contents($stream);

        // Close the stream
        fclose($stream);

        $this->setProgress(90);

        // Save the CSV data to a file in storage
        // In a real app, you might save to a tenant-specific path
        Storage::put("exports/tenant-{$this->tenantId}/{$this->filename}", $csv);

        $this->setProgress(100);
    }
}

/*
 * Usage example:
 *
 * use Filament\Facades\Filament;
 *
 * TenantAwareExportJob::dispatch(
 *     tenantId: Filament::getTenant()->id,
 *     data: User::all(),
 *     filename: 'users.csv'
 * );
 */
