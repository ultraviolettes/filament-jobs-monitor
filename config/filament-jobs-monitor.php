<?php

use Croustibat\FilamentJobsMonitor\Resources\QueueMonitorResource;

return [

    'connection' => null,

    'authorization' => [
        /**
         * What happens when an ability is covered by neither a policy on the
         * QueueMonitor model, a gate of the same name, nor an `authorize*()`
         * call on the plugin.
         *
         * false (default since v5): the ability is denied, so viewing job
         * payloads, retrying, deleting and clearing the logs all have to be
         * granted explicitly. Set it to true to restore the v4 behaviour,
         * where everyone reaching the panel could do everything.
         */
        'fallback' => false,
    ],

    'resources' => [
        'enabled' => true,
        'label' => 'filament-jobs-monitor::translations.model_label',
        'plural_label' => 'filament-jobs-monitor::translations.plural_model_label',
        'navigation_group' => 'filament-jobs-monitor::translations.navigation_group',
        'navigation_icon' => 'heroicon-o-cpu-chip',
        'navigation_sort' => null,
        'navigation_count_badge' => false,
        'resource' => QueueMonitorResource::class,
        'cluster' => null,
        /**
         * Configure the sub-navigation position for the resource pages.
         * Options: Filament\Pages\Enums\SubNavigationPosition::Top or ::Sidebar
         * Default: null (uses Filament default)
         */
        'sub_navigation_position' => null,
        /**
         * Table polling interval for the Job History and Pending jobs pages
         * (null to disable). Opt-in, because a poll also re-runs the four tab
         * count badges and the stats overview widget on the Job History page.
         */
        'polling_interval' => null,
    ],
    'failures' => [
        /**
         * Enable the "Failures" page: failed jobs grouped by signature
         * (exception class + job class + normalised message).
         */
        'enabled' => true,
        /**
         * Table polling interval (null to disable).
         */
        'polling_interval' => '10s',
    ],
    'pruning' => [
        'enabled' => true,
        'retention_days' => 7,
    ],
    'queues' => [
        'default',
    ],
    'tenancy' => [
        'enabled' => false,
        'model' => null,
        'column' => 'tenant_id',
        // Payload key inspected on queued jobs/listeners to resolve the tenant id
        // before falling back to the serialized command's `tenantId` property.
        'payload_key' => 'tenant_id',
    ],
];
