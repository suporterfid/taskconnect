# Task types and concurrency (R4)

Named task types live in `config/task_types.php` (env overrides via `TASK_TYPE_*`). Each type carries default `priority`, `weight`, `timeout_ms`, `max_attempts`, `egress_profile`, and a **per-type concurrency cap**. A **global in-flight ceiling** (`TASK_TYPE_GLOBAL_INFLIGHT`, default 4) bounds total weight across types.

## Persistence

Tasks store `task_type`, `priority`, `weight`, `timeout_ms`, and `egress_profile`. Create/update apply catalog defaults unless the client overrides governance fields. Unknown types are rejected; omitted `task_type` uses the `default` catalog entry (priority 0).

`egress_profile` is stored for R7 enforcement; delivery still uses the global outbound SSRF policy until then.

## Claiming

`DueTaskClaimer` (and `RetryClaimer`) order/select due work and skip rows that would exceed remaining capacity. **Per-type caps** limit how many open jobs of that type may run concurrently. The **global ceiling** is consumed in **weight** units so a heavy job (e.g. crawl weight 2) costs more of the shared hosting budget. In-flight accounting counts each `task_runs` row in `pending` or `running`, plus retry attempts that hold an active claim lease.

