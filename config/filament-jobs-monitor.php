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
         * true (default): everyone who can reach the panel can do everything,
         * as before v5.
         *
         * false (recommended): the ability is denied unless it is granted, so
         * reading job payloads — which routinely carry customer data — as well
         * as retrying, deleting and clearing the logs all have to be allowed
         * explicitly. Grant them with a policy, a gate or the plugin's
         * `authorize*()` methods; see the Authorization section of the README.
         */
        'fallback' => true,
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
         * Position of the Job History / Pending / Failures sub-navigation.
         * Options: Filament\Pages\Enums\SubNavigationPosition::Top, ::Start or
         * ::End (the `top`, `start` and `end` strings work too).
         * Default: null, which means Top — these pages are wide tables.
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
