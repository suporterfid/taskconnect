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

    /** Master switch for per-workspace DLQ webhooks (R13). */
    'failure_webhooks_enabled' => (bool) env('SCHEDULER_FAILURE_WEBHOOKS_ENABLED', true),

    'failure_webhook_connect_timeout' => (int) env('SCHEDULER_FAILURE_WEBHOOK_CONNECT_TIMEOUT', 2),

    'failure_webhook_total_timeout' => (int) env('SCHEDULER_FAILURE_WEBHOOK_TOTAL_TIMEOUT', 5),

    /**
     * Coalesce / debounce window (R11). Submits with the same coalesce_key in a
     * workspace within this many seconds reuse the first effective task.
     */
    'coalesce_window_seconds' => (int) env('SCHEDULER_COALESCE_WINDOW_SECONDS', 60),

    /**
     * Per-workspace picks per fairness round when interleaving due work (R12).
     * Weight 1 = classic round-robin across workspaces.
     */
    'fairness_workspace_weight' => (int) env('SCHEDULER_FAIRNESS_WORKSPACE_WEIGHT', 1),

    /**
     * Submission API rate limit (R15). Fixed window per workspace; stored in MySQL
     * (`rate_limit_buckets`). Override per workspace via environments.submit_rate_limit_per_minute.
     */
    'submit_rate_limit_per_minute' => (int) env('SCHEDULER_SUBMIT_RATE_LIMIT_PER_MINUTE', 60),

    'submit_rate_limit_window_seconds' => (int) env('SCHEDULER_SUBMIT_RATE_LIMIT_WINDOW_SECONDS', 60),

];
