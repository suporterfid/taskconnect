<?php

return [

    'payload_snapshots_days' => (int) env('RETENTION_PAYLOAD_SNAPSHOTS_DAYS', 30),

    'attempt_metadata_days' => (int) env('RETENTION_ATTEMPT_METADATA_DAYS', 180),

    'run_summary_days' => (int) env('RETENTION_RUN_SUMMARY_DAYS', 365),

    'audit_logs_days' => (int) env('RETENTION_AUDIT_LOGS_DAYS', 365),

    'api_idempotency_hours' => (int) env(
        'IDEMPOTENCY_ENQUEUE_TTL_HOURS',
        env('RETENTION_API_IDEMPOTENCY_HOURS', 24),
    ),

    'system_heartbeat_days' => (int) env('RETENTION_SYSTEM_HEARTBEAT_DAYS', 30),

];
