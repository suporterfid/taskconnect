# Workspace scoping (v1 Extension R1)

## Alias

For the v1 Extension Spec, a **workspace** is the existing **Environment** record.

| Spec field | Implementation |
|------------|----------------|
| `workspace_id` (API) | Environment `public_id` |
| DB FK | `environment_id` (unchanged) |
| Routes | Still `/tenants/{tenantId}/environments/{environmentId}/…` |

API JSON resources expose `workspace_id` alongside existing fields. Optional request body `workspace_id` on task create/update must match the route environment’s public id.

See also: [callback-contract.md](callback-contract.md) for outbound delivery idempotency headers (R3).

## Isolation

All tenant resources remain filtered by `tenant_id` **and** `environment_id`. Cross-environment access within the same tenant returns 404 (same as cross-tenant).

## Audit

`audit_logs.environment_id` records the workspace when the request carries an environment context (`tenant.context` middleware). List filter: `GET …/audit-logs?workspace_id={env_public_id}`.

## Claiming

`scheduler:execute-due` continues to claim due work **globally** (all tenants/workspaces) under MySQL leases. Settlement and API visibility remain workspace-scoped via `environment_id` on tasks/runs. Cross-workspace claim fairness uses weighted round-robin (R12); see `docs/architecture/fairness.md`.
