# Task types and concurrency (R4)

Named task types live in `config/task_types.php` (env overrides via `TASK_TYPE_*`). Each type carries default `priority`, `weight`, `timeout_ms`, `max_attempts`, `egress_profile`, and a **per-type concurrency cap**. A **global in-flight ceiling** (`TASK_TYPE_GLOBAL_INFLIGHT`, default 4) bounds total weight across types.

## Persistence

Tasks store `task_type`, `priority`, `weight`, `timeout_ms`, and `egress_profile`. Create/update apply catalog defaults unless the client overrides governance fields. Unknown types are rejected; omitted `task_type` uses the `default` catalog entry (priority 0).

`egress_profile` is stored for R7 enforcement; delivery still uses the global outbound SSRF policy until then.

## Claiming

`DueTaskClaimer` orders due work by `priority` DESC then `next_run_at`, over-fetches candidates, and skips rows that would exceed remaining per-type or global capacity. Capacity units are **weight**. In-flight accounting counts `task_runs` in `pending` or `running`, plus attempts with an active claim lease (retry path).
