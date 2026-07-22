<?php

/**
 * v1 Extension R4 — named task types with governance defaults (Q4).
 * Caps and weights are env-overridable; claiming uses weight units against caps.
 */
return [

    'global_inflight_ceiling' => (int) env('TASK_TYPE_GLOBAL_INFLIGHT', 4),

    'types' => [

        'document.convert' => [
            'priority' => (int) env('TASK_TYPE_CONVERT_PRIORITY', 5),
            'weight' => (int) env('TASK_TYPE_CONVERT_WEIGHT', 1),
            'timeout_ms' => (int) env('TASK_TYPE_CONVERT_TIMEOUT_MS', 20000),
            'max_attempts' => (int) env('TASK_TYPE_CONVERT_MAX_ATTEMPTS', 5),
            'egress_profile' => env('TASK_TYPE_CONVERT_EGRESS', 'internal'),
            'concurrency_cap' => (int) env('TASK_TYPE_CONVERT_CAP', 2),
        ],

        'site.crawl' => [
            'priority' => (int) env('TASK_TYPE_CRAWL_PRIORITY', 4),
            'weight' => (int) env('TASK_TYPE_CRAWL_WEIGHT', 2),
            'timeout_ms' => (int) env('TASK_TYPE_CRAWL_TIMEOUT_MS', 30000),
            'max_attempts' => (int) env('TASK_TYPE_CRAWL_MAX_ATTEMPTS', 3),
            'egress_profile' => env('TASK_TYPE_CRAWL_EGRESS', 'public-crawl'),
            'concurrency_cap' => (int) env('TASK_TYPE_CRAWL_CAP', 1),
        ],

        'kb.index' => [
            'priority' => (int) env('TASK_TYPE_INDEX_PRIORITY', 5),
            'weight' => (int) env('TASK_TYPE_INDEX_WEIGHT', 1),
            'timeout_ms' => (int) env('TASK_TYPE_INDEX_TIMEOUT_MS', 20000),
            'max_attempts' => (int) env('TASK_TYPE_INDEX_MAX_ATTEMPTS', 5),
            'egress_profile' => env('TASK_TYPE_INDEX_EGRESS', 'api'),
            'concurrency_cap' => (int) env('TASK_TYPE_INDEX_CAP', 2),
        ],

        'publish.build' => [
            'priority' => (int) env('TASK_TYPE_PUBLISH_PRIORITY', 6),
            'weight' => (int) env('TASK_TYPE_PUBLISH_WEIGHT', 2),
            'timeout_ms' => (int) env('TASK_TYPE_PUBLISH_TIMEOUT_MS', 60000),
            'max_attempts' => (int) env('TASK_TYPE_PUBLISH_MAX_ATTEMPTS', 3),
            'egress_profile' => env('TASK_TYPE_PUBLISH_EGRESS', 'internal'),
            'concurrency_cap' => (int) env('TASK_TYPE_PUBLISH_CAP', 1),
        ],

        'note.reminder' => [
            'priority' => (int) env('TASK_TYPE_REMINDER_PRIORITY', 8),
            'weight' => (int) env('TASK_TYPE_REMINDER_WEIGHT', 1),
            'timeout_ms' => (int) env('TASK_TYPE_REMINDER_TIMEOUT_MS', 10000),
            'max_attempts' => (int) env('TASK_TYPE_REMINDER_MAX_ATTEMPTS', 3),
            'egress_profile' => env('TASK_TYPE_REMINDER_EGRESS', 'internal'),
            'concurrency_cap' => (int) env('TASK_TYPE_REMINDER_CAP', 4),
        ],

        // Fallback for legacy / unspecified tasks.
        'default' => [
            'priority' => (int) env('TASK_TYPE_DEFAULT_PRIORITY', 0),
            'weight' => (int) env('TASK_TYPE_DEFAULT_WEIGHT', 1),
            'timeout_ms' => (int) env('TASK_TYPE_DEFAULT_TIMEOUT_MS', 15000),
            'max_attempts' => (int) env('TASK_TYPE_DEFAULT_MAX_ATTEMPTS', 5),
            'egress_profile' => env('TASK_TYPE_DEFAULT_EGRESS', 'internal'),
            'concurrency_cap' => (int) env('TASK_TYPE_DEFAULT_CAP', 4),
        ],

    ],

];
