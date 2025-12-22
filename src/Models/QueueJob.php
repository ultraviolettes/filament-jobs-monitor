<?php

namespace Croustibat\FilamentJobsMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class QueueJob extends Model
{
    protected $table = 'jobs';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'created_at' => 'datetime',
            'reserved_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('queue.connections.database.connection')
            ?? config('database.default');
    }

    public function getNameAttribute(): ?string
    {
        $payload = $this->payload;

        if (! is_array($payload)) {
            return null;
        }

        return $payload['displayName'] ?? $payload['job'] ?? null;
    }

    public function getStatusAttribute(): string
    {
        if ($this->reserved_at !== null) {
            return 'processing';
        }

        if ($this->available_at > now()) {
            return 'delayed';
        }

        return 'pending';
    }

    public static function isSupported(): bool
    {
        $driver = config('queue.default');

        return $driver === 'database';
    }
}
