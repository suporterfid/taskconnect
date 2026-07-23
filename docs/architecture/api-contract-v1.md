# v1 Extension API contract mapping (§6.1)

The v1 Extension Spec §6.1 JSON sketch is **illustrative**. TaskConnect keeps a **v0-compatible** nested API under `/api/v1`. This document is the official mapping between the sketch and the shipped contract.

**Stability rule:** do **not** rename fields or flatten routes in a breaking way without a versioned migration plan. Prefer additive aliases if consumers need sketch-literal names later.

## Route shape

| Spec sketch | Implementation |
|-------------|----------------|
| `POST /api/tasks` | `POST /api/v1/tenants/{tenantId}/environments/{environmentId}/tasks` |
| Workspace in body/`aud` | Route `{environmentId}` **is** the workspace (`Environment.public_id`, often `env_…`; GrandpaSSOn `aud` also accepts `workspace/<id>` — see `docs/architecture/workspace.md`) |
| `POST /api/pipelines/{name}/instances` | `POST /api/v1/tenants/{tenantId}/environments/{environmentId}/pipelines/{templateName}/instances` |

All mutating submission endpoints require `Idempotency-Key` where documented (R2) and are subject to submit rate limits (R15).

## Task resource field mapping

| Spec §6.1 | Implementation (`TaskResource` / DB) | Notes |
|-----------|--------------------------------------|-------|
| `id` `tsk_…` | `id` (task `public_id`) | Same idea |
| `tenant_id` `ten_…` | Route + membership context (not always echoed on task JSON) | Tenant isolation via middleware |
| `workspace_id` `wsp_…` | `workspace_id` = Environment `public_id` (`env_…`) | Stable **alias**; DB column remains `environment_id` |
| `task_type` | `task_type` | R4 catalog |
| `target_url` | `url_or_path` | Path relative to endpoint profile **or** absolute URL |
| `method` | `method` | |
| `headers` | `headers` | |
| `payload` | `body` (+ `content_type`, `query`) | Secrets via endpoint-profile / secret store (R9), not plaintext in logs |
| `priority` / `weight` / `timeout_ms` / `egress_profile` | same names | R4 / R7 |
| `max_attempts` | Inside `retry_policy` (`max_attempts`) + type catalog default | Not a top-level task field |
| `backoff` `{strategy,base_ms,cap_ms}` | `retry_policy.delay_seconds[]` (+ related policy fields) | Explicit delay schedule, not exponential object |
| `idempotency_key` | Enqueue: `Idempotency-Key` header (R2). Delivery: run-level key in callback headers (R3) | Not a create-body field for tasks |
| `coalesce_key` | `coalesce_key` | R11 |
| `run_at` | `run_at` when schedule kind is `once`; else null + `schedule` / `next_run_at` | R16 |
| `depends_on` / `on_success` / `on_failure` | **Pipeline template nodes only** (`docs/architecture/pipelines.md`) | Not on bare task create/update |
| `status` `pending\|claimed\|…` | Split: task `definition_status` + run `run_state` (`pending` / `running` / `retry_wait` / `succeeded` / `dead` / `blocked` / …) | No single task-level delivery status enum |
| `attempts[]` embedded | Separate task-run / attempt APIs (and DLQ show) | History is run-scoped |

## Callback headers (spec §6.3 vs shipped)

See `docs/architecture/callback-contract.md` and GrandpaSSOn docs. Shipped delivery headers use `Idempotency-Key`, `X-Task-Run-Id`, `X-Task-Attempt` (plus optional GrandpaSSOn bearer / `X-TC-*` HMAC when R8 flags are enabled). Spec names like `X-TC-Task-Id` / `X-TC-Workspace` are not required aliases today.

## Opt-in §6.1 aliases (issue #78)

Any endpoint returning `TaskResource` accepts `?aliases=spec-v1` to additively mirror sketch-literal field names alongside the v0 names above. **Off by default** — omitting the query param (or any other value) returns the unchanged v0 shape.

| Alias field | Mirrors | Semantics |
|-------------|---------|-----------|
| `target_url` | `url_or_path` | Verbatim copy. |
| `payload` | `body` (+ `content_type`) | `null` when `body` is `null`. JSON-decoded when `content_type` contains `json` and the body parses; otherwise the raw string (including when `content_type` is non-JSON, or JSON-typed but not valid JSON). |

Both v0 field names (`url_or_path`, `body`) remain present and canonical; the aliases are additive only, never a replacement.

## Related docs

- Workspace alias: `docs/architecture/workspace.md`
- Task types / `timeout_ms`: `docs/architecture/task-types.md`
- Pipelines: `docs/architecture/pipelines.md`
- Callbacks: `docs/architecture/callback-contract.md`
- Spec: `docs/superpowers/specs/2026-07-22-taskconnect-v1-extension-spec.md` §6.1
