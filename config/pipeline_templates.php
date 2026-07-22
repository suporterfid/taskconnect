<?php

/**
 * Named pipeline DAGs (R10).
 *
 * Each template is a map of node_key => node definition:
 * - task_type: catalog key from config/task_types.php
 * - depends_on: list of node_keys that must succeed before this node can start (fan-in)
 * - on_success: optional successor node_key enqueued when this node succeeds
 * - on_failure: optional successor node_key enqueued when this node fails terminally
 *
 * Edges from depends_on and on_success/on_failure must form a DAG (no cycles).
 */
return [
    'templates' => [
        /**
         * document.convert → kb.index → publish.build
         * (publish coalescing is R11; this template still materializes publish on index success)
         */
        'convert-index-publish' => [
            'description' => 'Convert a document, index it, then rebuild publish artifacts',
            'nodes' => [
                'convert' => [
                    'task_type' => 'document.convert',
                    'depends_on' => [],
                    'on_success' => 'index',
                    'on_failure' => null,
                ],
                'index' => [
                    'task_type' => 'kb.index',
                    'depends_on' => ['convert'],
                    'on_success' => 'publish',
                    'on_failure' => null,
                ],
                'publish' => [
                    'task_type' => 'publish.build',
                    'depends_on' => ['index'],
                    'on_success' => null,
                    'on_failure' => null,
                ],
            ],
        ],
    ],
];
