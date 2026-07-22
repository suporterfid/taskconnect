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
     * Per-workspace quantum per fairness round (R12/R17).
     * Weight 1 = classic one-pick (or one cost unit) per workspace per round.
     */
    'fairness_workspace_weight' => (int) env('SCHEDULER_FAIRNESS_WORKSPACE_WEIGHT', 1),

    /**
     * Fairness algorithm (R17).
     * - rr: each claimed item costs 1 (R12 weighted round-robin)
     * - wfq: deficit round-robin; cost = max(1, task.weight)
     */
    'fairness_mode' => env('SCHEDULER_FAIRNESS_MODE', 'wfq'),

    /**
     * Claim-time priority preemption (R17). When set, tasks with priority >= this
     * value may take up to priority_preemption_slots picks before normal fairness.
     * Null/empty disables preemption. Does not cancel in-flight deliveries.
     */
    'priority_preemption_min' => ($preempt = env('SCHEDULER_PRIORITY_PREEMPTION_MIN')) === null || $preempt === ''
        ? null
        : (int) $preempt,

    'priority_preemption_slots' => (int) env('SCHEDULER_PRIORITY_PREEMPTION_SLOTS', 1),

    /**
     * Submission API rate limit (R15). Fixed window per workspace; stored in MySQL
     * (`rate_limit_buckets`). Override per workspace via environments.submit_rate_limit_per_minute.
     */
    'submit_rate_limit_per_minute' => (int) env('SCHEDULER_SUBMIT_RATE_LIMIT_PER_MINUTE', 60),

    'submit_rate_limit_window_seconds' => (int) env('SCHEDULER_SUBMIT_RATE_LIMIT_WINDOW_SECONDS', 60),

    /**
     * S10: write workspace-scoped audit_logs rows for claim leases and delivery
     * terminal outcomes (succeeded / retry_wait / dead / blocked). Metadata only.
     */
    'audit_claims' => filter_var(env('SCHEDULER_AUDIT_CLAIMS', true), FILTER_VALIDATE_BOOL),

    'audit_deliveries' => filter_var(env('SCHEDULER_AUDIT_DELIVERIES', true), FILTER_VALIDATE_BOOL),

];
