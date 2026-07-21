# MVP P0/P1 Design

## Goal

Close the blockers that prevent TaskConnect from meeting `docs/http-task-scheduler-spec.md` §28 for user workflow, reliability, security, and operability — without Phase 4 SaaS features.

## Constraints

- Shared hosting: PHP/MySQL + per-minute cron only; extend MySQL claim leases, do not add brokers.
- Domain / Application / Infrastructure / Http layering stays intact.
- Frontend continues to use Vue 3 + Pinia + vue-i18n; keep existing layout/visual language.
- Track work in commits; GitHub issue API unavailable to this agent (403).

## Approach (chosen)

**Single cohesive MVP hardening branch** that fixes the execution gap first, then authorization and notifications, then frontend contracts and operator UI.

Rejected alternatives:

1. **API-only MVP** — leaves §28 user workflow unmet.
2. **Frontend-first** — would polish a broken pending-run path.

## P0 — Core correctness

### 1. Pending run execution

Add `PendingRunClaimer` that claims `task_runs` in `run_state=pending` with a pending attempt (manual, test, and manual-retry paths). Invoke it from `SchedulerCycleRunner::executeDue()` after due-task claiming so one cron minute drains both scheduled and pending work.

Manual retry stays `pending` (not `retry_wait`) so automatic delay retries and operator-forced retries remain distinct.

### 2. Authorization

Refactor `TaskPolicy` / `TaskRunPolicy` to `Authenticatable` + `InteractsWithTenantAccess`. Restrict cancel/retry/operate to tenant admin/member (or API key scopes). Add `tasks:read`, `tasks:write`, `tasks:operate` to allowed API key permissions.

### 3. Failure notifications

Inject `FailureNotifier` into `AttemptExecutor` and call `notifyDeadRun()` when a run reaches `dead`.

### 4. Archived environments

Reject create/activate/run-now/test (and analogous secret/profile/key creates) when `environment.archived_at` is set, returning a clear 422.

## P1 — Operator workflow

### Frontend contracts

Align `types.ts` with backend resources (`definition_status`, `run_state`, timestamps). Parse nested `{ error: { code, message, details, request_id } }`. Watch tenant/environment in `useAsyncData` and reload.

### Task + secrets UX

Expand wizard (hydrate edit, endpoint profile picker, schedule kinds, retry, test step). Task detail lifecycle actions. Secrets CRUD page. Task list columns for next/last run.

### Runs + dashboard

Run list/detail with attempts, redacted diagnostics, cancel/retry. Dashboard consumes heartbeat, dead/retry counts, upcoming tasks.

### Operability

Password reset UI; user preferences PATCH + settings page; audit log read API + basic UI; complete retention pruning (attempt metadata, run summaries, audit logs); remove duplicate stale-claim logic from `RetentionCleaner` (authoritative path remains `StaleClaimRecovery`).

## Out of scope (P2+)

Overlapping-cron stress test, Playwright e2e, CI release-zip validation, rate limiting, cursor pagination, tenant outbound allowlists, custom success status ranges.
