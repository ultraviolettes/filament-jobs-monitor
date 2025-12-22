<?php

namespace Croustibat\FilamentJobsMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    protected $table = 'failed_jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('queue.failed.database')
            ?? config('database.default');
    }
}
