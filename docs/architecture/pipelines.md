# Pipelines (R10)

Named DAG templates chain task types with `on_success` / `on_failure` edges and optional `depends_on` fan-in. Cycles are rejected when a template is loaded or an instance is created.

## Ship template

`convert-index-publish`:

```
document.convert  --on_success-->  kb.index  --on_success-->  publish.build
```

(`publish.build` coalescing is R11; this release still materializes one publish node per successful index.)

## API (workspace-scoped)

```
GET  /api/v1/tenants/{tenant}/environments/{env}/pipelines
POST /api/v1/tenants/{tenant}/environments/{env}/pipelines/{template}/instances
GET  /api/v1/tenants/{tenant}/environments/{env}/pipelines/{template}/instances/{id}
```

`POST …/instances` requires an `Idempotency-Key` (same middleware as task create) and a `nodes` map with delivery config for **every** template node (`url_or_path` / `method` / `body` / …).

Root nodes (empty `depends_on`) are materialized immediately as Active tasks with a Pending run (`next_run_at` null so the scheduled claimer ignores them; `PendingRunClaimer` / `scheduler:execute-due` picks them up).

## Settlement

When a pipeline-linked run reaches a terminal state (`succeeded`, `dead`, `blocked`):

- **Success** → mark node succeeded; materialize `on_success` (and any fan-in dependents whose `depends_on` are all succeeded).
- **Failure** → mark node failed; materialize `on_failure` if present; otherwise halt pending downstream nodes. The failed run remains `dead` (DLQ).

Instance status becomes `succeeded` when every node is terminal and none failed/halted; otherwise `failed`.

Templates live in `config/pipeline_templates.php`.
