# Dead-letter queue (R6)

For P0, the dead-letter queue (DLQ) **is** `task_runs` with `run_state=dead`. There is no separate DLQ table.

## Operator CLI

```bash
php artisan tasks:dlq:list [--workspace=env_…] [--type=document.convert] [--limit=50]
php artisan tasks:dlq:show {run_public_id}
php artisan tasks:dlq:replay {run_public_id}
php artisan tasks:dlq:replay --type=document.convert [--workspace=env_…] [--limit=50]
```

`replay` creates a **new** Pending run with attempt count 1 and a **new** delivery `Idempotency-Key` group (see `docs/architecture/callback-contract.md`). The original dead run remains for history until retention deletes it. Replay is audited as `dlq.replayed`.

On DLQ arrival, optional per-workspace email/webhook alerts fire (R13); see `docs/architecture/dlq-alerting.md`.

API `POST …/task-runs/{id}/retry` still reuses the same delivery key (in-run retry semantics). Prefer the DLQ CLI when the operator wants a fresh delivery group after exhaustion.

## Retention

`RETENTION_DEAD_RUNS_DAYS` (default **30**, Q6) ages dead runs via `scheduler:maintenance` before the general `RETENTION_RUN_SUMMARY_DAYS` terminal prune.
