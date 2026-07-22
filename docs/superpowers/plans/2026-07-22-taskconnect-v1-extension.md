# TaskConnect v1 Extension — Implementation Plan

**Origin:** `docs/superpowers/specs/2026-07-22-taskconnect-v1-extension-spec.md`  
**Base authority:** `docs/http-task-scheduler-spec.md` (wins on v0 conflicts)  
**Companion:** GrandpaSSOn v1 extension (`tasks:callback`, `tasks:write`)  
**Date:** 2026-07-22

## Problem frame

v0 schedules tenant/environment HTTP tasks with MySQL claim leases + cron. The knowledge-platform needs workspace-scoped jobs, end-to-end idempotency, resource governance, DLQ, named egress, and GrandpaSSOn-delegated auth — **without** breaking shared-hosting constraints (no Redis/daemon).

## Scope boundary

| In (this plan) | Out until later phases |
|----------------|-------------------------|
| P0 R1–R9; P1 R10+ as issues land | Remaining P1/P2 until mirrored by issues |
| Docs + issues + migrations that keep v0 green | In-process convert/crawl/embed (N1) |

**Original stop line (§11):** R1–R9 complete — **done**. P1 proceeds via GitHub issues (#28+).

## Architecture decisions

1. **Workspace = Environment (v1 alias).** Map `workspace_id` API field to existing `environment_id` / `Environment` public id. Avoid a second hierarchy until Workspace gains distinct semantics (quotas beyond env). Document alias in API resources; keep DB column `environment_id` unless a later rename migration is justified.
2. **Task type = config + optional DB table.** Seed named types (`document.convert`, `site.crawl`, `kb.index`, `publish.build`, `note.reminder`) with priority/weight/timeout/caps/egress from `config/task_types.php` + env overrides (Q4 defaults). Persist `task_type` on tasks.
3. **DLQ = `run_state=dead` + CLI.** No separate DLQ table for P0; artisan commands list/show/replay dead runs. Replay = new attempt group / new delivery idempotency key (per §6.6).
4. **Egress profiles wrap `OutboundPolicy`.** Profiles `internal` | `public-crawl` | `api` select allowlists / deny rules; keep DNS-pinned transport. Ship `public-crawl` unused by default (Q3).
5. **GrandpaSSOn is required for R8** (Q1 default: no local-only auth path). Gate R8 behind config + cross-repo `tasks:write` ticket; keep Sanctum/API-key for SPA/v0 until cutover flags allow dual mode during migration.
6. **Delivery idempotency header:** Prefer Extension Spec `Idempotency-Key` while retaining `X-Task-Idempotency-Key` for one release if needed for compatibility.

## Sequencing (P0 only)

```mermaid
flowchart TD
  epic[Epic: v1 Extension P0]
  r1[R1 Workspace alias]
  r2[R2 Enqueue idempotency]
  r3[R3 Delivery idempotency align]
  r4[R4 Task types + caps]
  r5[R5 Heartbeat budget]
  r6[R6 DLQ CLI]
  r7[R7 Egress profiles]
  r8[R8 GrandpaSSOn auth]
  r9[R9 Secret/zip hygiene]
  epic --> r1 --> r2 --> r3 --> r4 --> r5 --> r6 --> r7 --> r8 --> r9
  r4 -.-> r7
```

Implement **in order R1→R9**. Do not start R(n+1) until R(n) acceptance tests pass (spec §0). R7 can share design work with R4 (`egress_profile` on type) but claimer caps (R4) should land before profile selection is enforced on delivery.

## Gap summary (current main)

| Req | Status | Notes |
|-----|--------|-------|
| R1 | **Done** (#17) | Environment ↔ workspace_id API alias; audit_logs.environment_id; see `docs/architecture/workspace.md` |
| R2 | **Done** (#18) | Required Idempotency-Key on create/run-now; workspace-scoped; 24h TTL; 200 replay; prune via retention |
| R3 | **Done** (#19) | Outbound `Idempotency-Key` + deprecated `X-Task-Idempotency-Key`; `docs/architecture/callback-contract.md` |
| R4 | **Done** (#20) | `config/task_types.php` + task columns; claimer priority + weight caps; see CHANGELOG R4 |
| R5 | **Done** (#21) | `TickBudget` + chunked claim-execute in `SchedulerCycleRunner`; see `docs/deployment/cron.md` |
| R6 | **Done** (#22) | DLQ = `run_state=dead`; `tasks:dlq:*` CLI + 30d retention; `docs/architecture/dlq.md` |
| R7 | **Done** (#23) | Named egress profiles on DNS-pinned SSRF; `docs/architecture/egress-profiles.md` |
| R8 | **Done** (#24) | GrandpaSSOn outbound bearer+HMAC + inbound introspection (flags off by default); `docs/architecture/grandpasson-auth.md` |
| R9 | **Done** (#25) | Release zip secret scan + `.env.example` v1 placeholders; `docs/architecture/secret-hygiene.md` |
| R10 | **Done** (#28) | Pipeline templates + instance API + settlement; `docs/architecture/pipelines.md` |
| R11 | **Done** (#29) | `coalesce_key` + window; `docs/architecture/coalesce.md` |
| R12 | **Done** (#30) | Workspace weighted RR claiming; `docs/architecture/fairness.md` |
| R13 | **Done** (#31) | Per-workspace DLQ email/webhook alerts; `docs/architecture/dlq-alerting.md` |
| R15 | **Done** (#33) | DB-backed submit rate limits; `docs/architecture/submit-rate-limiting.md` |
| R14 | **Done** (#32) | Operator UI: task governance fields, DLQ page, pipelines pages + REST |
| R16 | **Done** (part of #34) | Top-level `run_at` + optional five-field `cron`; `docs/architecture/delayed-schedules.md` |
| R17 | **Done** (#56) | WFQ deficit claiming + claim-time priority preemption; `docs/architecture/fairness.md` |
| R18 | **Done** (part of #34) | Prometheus-style `/platform/metrics`; `docs/architecture/metrics.md` |

## Open questions (defaults applied)

Audit record: `docs/architecture/open-questions.md` (closes [#35](https://github.com/suporterfid/taskconnect/issues/35)).

| # | Default we will implement | Status |
|---|---------------------------|--------|
| Q1 | Cross-repo GrandpaSSOn `tasks:write`; dual-mode inbound until ready | Applied |
| Q2 | Delayed `run_at` formalized; recurring stays structured kinds (+ optional cron) | Applied (R16) |
| Q3 | Ship `public-crawl` profile unused by non-crawl types | Applied |
| Q4 | Global 4; convert 2; crawl 1; index 2; reminder 4 | Applied |
| Q5 | Introspect inbound; cache outbound client-credentials | Applied |
| Q6 | DLQ 30d; enqueue idempotency 24h | Applied |

## GitHub issues

Canonical tracking (created 2026-07-22):

| Issue | Title |
|-------|-------|
| [#16](https://github.com/suporterfid/taskconnect/issues/16) | Epic: P0 stop line (R1–R9) — **closed** (R1–R9 done) |
| [#17](https://github.com/suporterfid/taskconnect/issues/17) | P0/R1 Workspace scoping |
| [#18](https://github.com/suporterfid/taskconnect/issues/18) | P0/R2 Enqueue idempotency |
| [#19](https://github.com/suporterfid/taskconnect/issues/19) | P0/R3 Delivery idempotency |
| [#20](https://github.com/suporterfid/taskconnect/issues/20) | P0/R4 Task types + caps |
| [#21](https://github.com/suporterfid/taskconnect/issues/21) | P0/R5 Heartbeat budget |
| [#22](https://github.com/suporterfid/taskconnect/issues/22) | P0/R6 DLQ CLI |
| [#23](https://github.com/suporterfid/taskconnect/issues/23) | P0/R7 Egress profiles |
| [#24](https://github.com/suporterfid/taskconnect/issues/24) | P0/R8 GrandpaSSOn auth |
| [#25](https://github.com/suporterfid/taskconnect/issues/25) | P0/R9 Secret / release hygiene |
| [#26](https://github.com/suporterfid/taskconnect/issues/26) | Cross-repo GrandpaSSOn `tasks:write` |
| [#27](https://github.com/suporterfid/taskconnect/issues/27) | Epic: P1 (after P0 review) — **closed** (R10–R15 done) |
| [#28](https://github.com/suporterfid/taskconnect/issues/28)–[#33](https://github.com/suporterfid/taskconnect/issues/33) | P1 R10–R15 |
| [#34](https://github.com/suporterfid/taskconnect/issues/34) | Epic: P2 R16–R18 — **closed** (R16–R18 done) |
| [#56](https://github.com/suporterfid/taskconnect/issues/56) | P2/R17 Priority preemption / refined WFQ |
| [#35](https://github.com/suporterfid/taskconnect/issues/35) | Open questions Q1–Q6 defaults |

Spec copy: `docs/superpowers/specs/2026-07-22-taskconnect-v1-extension-spec.md`.

## Definition of Done (each P0 issue)

- Idempotent migration if schema changes; no destructive v0 changes
- Unit + feature tests for acceptance criteria; `tc test` green
- No hardcoded secrets; config via `.env` / `.env.example`
- Docs under `docs/` updated when behavior is user-visible
- `tc release` still produces a clean zip (R9 hardens the check)
