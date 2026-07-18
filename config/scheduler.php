<?php

return [

    'claim_batch' => (int) env('SCHEDULER_CLAIM_BATCH', 20),

    'retry_batch' => (int) env('SCHEDULER_RETRY_BATCH', 20),

    'claim_ttl_minutes' => (int) env('SCHEDULER_CLAIM_TTL_MINUTES', 10),

    'target_duration_seconds' => (int) env('SCHEDULER_TARGET_DURATION_SECONDS', 45),

    'failure_emails_enabled' => (bool) env('SCHEDULER_FAILURE_EMAILS_ENABLED', true),

];
