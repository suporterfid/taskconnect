<?php

return [

    'claim_batch' => (int) env('SCHEDULER_CLAIM_BATCH', 20),

    'retry_batch' => (int) env('SCHEDULER_RETRY_BATCH', 20),

    'claim_ttl_minutes' => (int) env('SCHEDULER_CLAIM_TTL_MINUTES', 10),

    'target_duration_seconds' => (int) env('SCHEDULER_TARGET_DURATION_SECONDS', 45),

    /** Seconds reserved before PHP max_execution_time / target so the tick exits cleanly (R5). */
    'budget_safety_margin_seconds' => (int) env('SCHEDULER_BUDGET_SAFETY_MARGIN_SECONDS', 5),

    /** Claim-execute chunk size so budget stop does not leave a large unused lease batch. */
    'claim_chunk' => (int) env('SCHEDULER_CLAIM_CHUNK', 5),

    'failure_emails_enabled' => (bool) env('SCHEDULER_FAILURE_EMAILS_ENABLED', true),

];
