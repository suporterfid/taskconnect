

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


Named DAG templates chain task types with `on_success` / `on_failure` edges and optional `depends_on` fan-in. Cycles are rejected when a template is loaded or an instance is created.

## Ship template

`convert-index-publish`:

```
document.convert  --on_success-->  kb.index  --on_success-->  publish.build
```

(`publish.build` coalescing is R11 â€” default key `pipeline:{template}:publish.build` within the coalesce window; see `docs/architecture/coalesce.md`.)

## API (workspace-scoped)

```
GET  /api/v1/tenants/{tenant}/environments/{env}/pipelines
POST /api/v1/tenants/{tenant}/environments/{env}/pipelines/{template}/instances
GET  /api/v1/tenants/{tenant}/environments/{env}/pipelines/{template}/instances/{id}
```

`POST â€¦/instances` requires an `Idempotency-Key` (same middleware as task create) and a `nodes` map with delivery config for **every** template node (`url_or_path` / `method` / `body` / â€¦).

Root nodes (empty `depends_on`) are materialized immediately as Active tasks with a Pending run (`next_run_at` null so the scheduled claimer ignores them; `PendingRunClaimer` / `scheduler:execute-due` picks them up).

## Settlement

When a pipeline-linked run reaches a terminal state (`succeeded`, `dead`, `blocked`):

- **Success** â†’ mark node succeeded; materialize `on_success` (and any fan-in dependents whose `depends_on` are all succeeded).
- **Failure** â†’ mark node failed; materialize `on_failure` if present; otherwise halt pending downstream nodes. The failed run remains `dead` (DLQ).

Instance status becomes `succeeded` when every node is terminal and none failed/halted; otherwise `failed`.

Templates live in `config/pipeline_templates.php`.
