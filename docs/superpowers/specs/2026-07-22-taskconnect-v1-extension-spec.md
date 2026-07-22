# TaskConnect — v1 Extension Spec

**Workspace-scoped jobs · Idempotency · Pipelines · Dead-letter · Egress profiles · Delegated auth**

- **Status:** Draft for implementation
- **Audience:** Cursor coding agents working in `suporterfid/taskconnect`
- **Relationship to base spec:** This document is **additive** to `docs/http-task-scheduler-spec.md` (the product/protocol authority). Where this document and the base spec conflict on existing v0 behavior, **the base spec wins**. This document only adds new capabilities and constrains how they are built.
- **Companion spec:** `grandpasson-spec-v1-extension.md`. TaskConnect authenticates via GrandpaSSOn and depends on scopes defined there (`tasks:callback`, and a new `tasks:write` — see §8 and §12/Q1).
- **Primary consumer:** the Obsidian-like notes/knowledge app, which registers jobs (document conversion, website crawl, indexing/embedding, publish builds, note-driven reminders) and receives TaskConnect's callbacks.

---

## 0. How Cursor agents should use this document

1. **Do not break v0.** Existing behavior — MySQL-backed claiming, at-least-once HTTP delivery, SSRF protection, encrypted secrets, and the `scheduler:execute-due` / `scheduler:retry-due` / `scheduler:maintenance` cron trio — must keep passing its current tests.
2. **Work top-down by priority.** Implement **P0** first, in order. Do not start a P1 until every P0 has passing tests and its acceptance criteria met.
3. **Definition of Done for any requirement:**
   - Code follows the existing Laravel-12 modular layout (`app/`, `routes/`, `database/`, `frontend/`, `tests/`).
   - An **idempotent** migration exists (safe to re-run on upgrade; no destructive change to v0 tables).
   - Unit + integration tests cover the acceptance criteria; add Playwright E2E where a UI surface is touched. `tc test` green.
   - No secret/credential/token is hardcoded — everything from `.env` (see §8).
   - `tc release` still emits a working shared-hosting zip under `dist/` containing no secrets.
4. **Respect the architectural boundary (§3/N1).** TaskConnect is a **scheduler + dispatcher of HTTP calls**. It does **not** parse documents, crawl-and-extract, or embed in-process by default. Heavy compute happens in the *callback target* (the notes app), one unit of work per task.
5. **Stop at the §11 stop line** when the P0 set is green. Do not scope-creep into P1/P2.
6. **When ambiguous,** consult §12 Open Questions; if unresolved, leave `TODO(spec): <question>` and take the safest documented default. Never weaken SSRF or auth to make a test pass.

---

## 1. Problem statement

TaskConnect v0 is a multi-tenant HTTP task scheduler: MySQL-backed claiming, at-least-once delivery with SSRF protection, driven by a one-minute cron. That is enough to fire scheduled HTTP calls.

The knowledge-platform work needs more from the background plane:

1. **Workspace granularity.** Jobs belong to a *workspace within a tenant*, not just a tenant. Isolation and fairness must hold at the workspace level.
2. **Safe at-least-once semantics end-to-end.** At-least-once delivery means duplicates are expected. Without idempotency at both enqueue and delivery, a retried document-conversion or publish-build runs twice and corrupts state.
3. **Resource governance on shared hosting.** Heavy batches (convert 50 PDFs, crawl a site, embed a vault) must not exhaust a shared cPanel account or starve light, time-sensitive jobs (reminders). This needs job types, priorities, weights, and concurrency caps.
4. **Pipelines.** The real workflows are chains — *convert → index → publish* — not isolated calls. Success/failure must propagate.
5. **A failure surface.** A corrupt file or a dead target must land in a dead-letter queue that a human can inspect and replay — never fail silently.
6. **Controlled egress.** Some jobs must reach the public internet (crawling, embedding APIs) while the default must stay locked down. SSRF protection needs to become an explicit, per-job egress policy.
7. **Delegated identity.** Callbacks must carry a verifiable identity instead of a static shared secret (the failure class being remediated elsewhere).

---

## 2. Goals

- **G1 — Workspace-scoped scheduling** with isolation and fairness at tenant *and* workspace level.
- **G2 — Exactly-once *effect* on top of at-least-once *delivery*** via idempotency at enqueue and on every callback.
- **G3 — Resource governance:** job types with priority, weight, per-type concurrency caps, and a global in-flight ceiling that respects shared-hosting limits.
- **G4 — Pipelines:** declarative chaining (`on_success` / `on_failure` / `depends_on`) and reusable named templates (e.g. `convert→index→publish`).
- **G5 — Operable failure handling:** a dead-letter queue with inspect + replay.
- **G6 — Explicit egress policy:** named profiles, default-deny-external, hardened against SSRF/DNS-rebinding, with an opt-in public profile for crawling/APIs.
- **G7 — Delegated auth via GrandpaSSOn:** bearer identity + HMAC integrity on callbacks; introspected tokens on inbound submission.
- **G8 — Still runs unchanged on cPanel-style shared hosting** (PHP 8.2+, MySQL 8.0+, one-minute cron, no daemon, `exec` disabled, deploy-by-zip).

**Measurable outcomes**

- A duplicate delivery of the same task produces **one** effect (verified by test).
- A 50-file conversion batch never exceeds the configured concurrency cap and never blocks a due reminder by more than one cron tick.
- A failed task after max attempts is inspectable and replayable within one command.
- Zero static shared secrets on callbacks; zero secrets in the release zip (verified by test).

---

## 3. Non-goals (explicit)

- **N1 — TaskConnect does not do heavy compute in-process.** It dispatches HTTP calls; the notes app parses PDFs/DOCX/PPTX/XLSX, crawls-and-extracts, and embeds — **one file / page-range / URL per task**. (Exception: the opt-in `public-crawl`/`api` egress profiles in §6.5, for cases where TaskConnect itself must fetch.)
- **N2 — No long-running worker daemon.** The one-minute cron heartbeat remains the only executor. No queue worker process.
- **N3 — No new queue backend.** Stays MySQL-backed claiming. No Redis, no SQS.
- **N4 — Does not own identity or authorization policy.** Delegates to GrandpaSSOn; enforces the claims it receives.
- **N5 — Does not store workspace content.** Payloads reference content by id/URL; TaskConnect persists only what it needs to schedule and audit.
- **N6 — No distributed multi-node coordination.** Single shared-hosting account assumed; claiming prevents double-processing within it.

---

## 4. Domain model & terminology

| Term | Meaning |
|------|---------|
| **Task** | One scheduled HTTP delivery to a target URL with a payload, retry policy, and settlement outcome. Extends v0. |
| **Task Type** | A named class of task (e.g. `document.convert`, `site.crawl`, `kb.index`, `publish.build`, `note.reminder`) carrying default priority, weight, timeout, concurrency cap, and egress profile. New. |
| **Workspace scope** | Every task carries `tenant_id` **and** `workspace_id`. New. |
| **Attempt** | A single delivery try of a task. Existing (retry) formalized. |
| **Idempotency key** | Client-supplied key deduping enqueue; and a TaskConnect-supplied header deduping delivery. New. |
| **Coalesce key** | Groups rapid re-triggers into one effective task within a window (debounce). New. |
| **Pipeline** | A named DAG template of task types with success/failure edges (e.g. `convert→index→publish`). New. |
| **Dead-letter (DLQ)** | Terminal state for tasks that exhausted attempts; inspectable + replayable. New. |
| **Egress profile** | Named outbound policy governing which destinations a task may reach and how (§6.5). New. |
| **Heartbeat tick** | One cron invocation of `scheduler:execute-due`, bounded by a wall-clock budget (§7). |
| **Service client** | TaskConnect's GrandpaSSOn machine identity (client-credentials) used to sign/authenticate callbacks. New. |

---

## 5. Requirements (MoSCoW)

### P0 — Must have

- **R1. Workspace scoping.** `workspace_id` (with `tenant_id`) on every task; all queries, claiming, isolation, and audit are workspace-aware.
- **R2. Enqueue idempotency.** `Idempotency-Key` on the submission API dedupes within a window; same key → same task, no duplicate row.
- **R3. Delivery idempotency.** TaskConnect sends a stable `Idempotency-Key` header on every callback (constant across retries of the same task) so receivers dedupe.
- **R4. Task types + governance.** Task types carrying `priority`, `weight`, `timeout_ms`, `max_attempts`, `egress_profile`, and a **per-type concurrency cap**, plus a **global in-flight ceiling**. Claiming respects caps.
- **R5. Bounded, overlap-safe heartbeat.** `scheduler:execute-due` runs under a wall-clock budget (stops claiming before PHP `max_execution_time`), processes a bounded batch, and is safe if a tick overlaps the next.
- **R6. Dead-letter queue.** Tasks exhausting `max_attempts` move to DLQ with last error, response snippet, and attempt history; CLI to **list, inspect, replay**.
- **R7. Egress profiles + hardened SSRF.** Named profiles; default `internal` (allowlist of RP hosts, deny all else); opt-in `public-crawl` / `api`. DNS-resolve-then-pin, deny RFC1918/loopback/link-local/`169.254.169.254`/IPv6 ULA on non-internal profiles. Enforced on every delivery.
- **R8. Delegated callback auth.** Outbound callbacks carry a GrandpaSSOn client-credentials **bearer** token (scope `tasks:callback`) **and** an HMAC signature (`X-TC-Signature`) over body+timestamp+nonce. Inbound submission validated via GrandpaSSOn introspection (scope `tasks:write`), enforcing workspace `aud`.
- **R9. Secret hygiene.** All secrets/keys/signing material from `.env`; task payload secrets encrypted at rest (extends v0); build test fails on any hardcoded credential in the zip.

### P1 — Should have

- **R10. Pipelines / chaining.** `on_success` / `on_failure` successor task templates and `depends_on` fan-in; reusable named pipeline templates; DAG validated (no cycles) at enqueue. Ships with a `convert→index→publish` template.
- **R11. Coalescing / debounce.** `coalesce_key` + window so bursty triggers (e.g. many edits → one `publish.build`) collapse to one effective task.
- **R12. Fairness scheduling.** Weighted round-robin across workspaces/tenants so one workspace's batch cannot starve others.
- **R13. DLQ alerting hook.** Webhook/email notification on DLQ arrival (per workspace, configurable).
- **R14. Operator UI.** Extend the Vue 3 SPA with task list/detail, DLQ inspect/replay, and pipeline run visibility (workspace-scoped).
- **R15. Submission rate limiting.** DB-backed limits on the submission API (no Redis).

### P2 — Could have

- **R16. Formalized recurring + delayed schedules** (cron expressions for recurring; `run_at` for delayed reminders) if not already first-class in v0.
- **R17. Priority preemption / refined weighted-fair queuing.**
- **R18. Metrics endpoint** (Prometheus-style text) for queue depth, in-flight, DLQ size, per-type latency.

### Won't have (this iteration)

- In-process document parsing/crawling/embedding (stays in the notes app, N1); Redis/alternate backends (N3); multi-node workers (N6); real-time push to clients. Revisit later.

---

## 6. Protocol & data contracts

> New endpoints live under the existing app, served from `public/`. JSON in/out. HTTPS required. All endpoints and claiming are workspace-scoped.

### 6.1 Task schema (additions to v0)

```jsonc
{
  "id": "tsk_...",
  "tenant_id": "ten_...",
  "workspace_id": "wsp_...",        // R1 (new)
  "task_type": "document.convert",  // R4 (new)
  "target_url": "https://notes.internal/internal/convert",
  "method": "POST",
  "headers": { "...": "..." },
  "payload": { "file_id": "..." },  // secrets encrypted at rest (R9)
  "priority": 5,                    // R4 (higher = sooner)
  "weight": 3,                      // R4 (resource cost unit)
  "timeout_ms": 20000,              // R4 (delivery timeout)
  "max_attempts": 5,
  "backoff": { "strategy": "exponential", "base_ms": 2000, "cap_ms": 300000 },
  "egress_profile": "internal",     // R7 (new)
  "idempotency_key": "conv:file:...", // R2 (new; unique per workspace+window)
  "coalesce_key": null,             // R11 (P1)
  "run_at": null,                   // delayed/reminder; null = ASAP
  "depends_on": [],                 // R10 (P1; fan-in)
  "on_success": null,               // R10 (P1; successor template)
  "on_failure": null,               // R10 (P1)
  "status": "pending",              // pending|claimed|delivering|succeeded|failed|dead
  "attempts": []                    // history
}
```

### 6.2 `POST /api/tasks` — enqueue (extended)

- **Auth:** GrandpaSSOn bearer token, scope `tasks:write`, `aud` matching `workspace_id` (§8).
- **Header:** `Idempotency-Key: <key>` (R2).

**Acceptance criteria:**
```
Given a submission with Idempotency-Key "K" for workspace W,
When the same Idempotency-Key "K" is submitted again within the dedupe window,
Then no second task row is created and the original task is returned (200/idempotent).

Given a token whose aud does not include workspace_id W,
When it submits a task for W,
Then the request is rejected 403 and the attempt is audited.
```

### 6.3 Callback contract (what TaskConnect sends the target) — R3, R8

Every delivery includes:
```
Authorization: Bearer <grandpasson client-credentials token, scope tasks:callback>
Idempotency-Key: <stable per task; constant across retries>     # R3
X-TC-Task-Id: tsk_...
X-TC-Workspace: wsp_...
X-TC-Timestamp: <unix seconds>
X-TC-Nonce: <random>
X-TC-Signature: <HMAC-SHA256(secret, timestamp + "." + nonce + "." + raw_body)>   # R8
```

- Receiver MUST verify HMAC + timestamp freshness (reject skew beyond N seconds) + nonce non-replay, then dedupe on `Idempotency-Key`.
- **Success** = 2xx. **Retryable** = 5xx / timeout / connection error. **Permanent fail** = 4xx (except 429 → retry with backoff).

**Acceptance criteria:**
```
Given a task delivered twice due to at-least-once retry,
When the receiver honors the constant Idempotency-Key,
Then the effect occurs once (verified by a receiver test double counting effects).

Given a callback with a tampered body,
When the receiver verifies X-TC-Signature,
Then verification fails and the receiver rejects the call.
```

### 6.4 Pipelines (R10 / P1)

- A **pipeline template** is a named DAG of task-type nodes with `on_success`/`on_failure` edges and optional `depends_on` fan-in.
- `POST /api/pipelines/{name}/instances` enqueues an instance scoped to a workspace; TaskConnect materializes the first node(s) and enqueues successors as nodes settle.
- Cycles rejected at creation. A node's failure routes to its `on_failure` edge or, if none, to DLQ (and halts dependent successors).

Ships with:
```
convert → index → publish
  document.convert (per file)  --on_success-->  kb.index (per doc)  --on_success-->  publish.build (coalesced)
```

### 6.5 Egress profiles (R7)

| Profile | Allows | Hard-denies | Extras |
|---------|--------|-------------|--------|
| `internal` (default) | Allowlisted RP hostnames only | Everything else, incl. all private ranges | Strict; used by nearly all jobs |
| `public-crawl` | Public unicast internet | RFC1918, loopback, link-local, `169.254.169.254`, IPv6 ULA/link-local | Per-host rate limit, max redirects, response-size cap, optional `robots.txt` |
| `api` | Allowlisted external API hosts (e.g. embedding provider) | All private ranges | Per-host rate limit; used by `kb.index` embedding calls |

- **DNS-rebinding protection:** resolve host → validate resolved IP(s) against profile → connect to the **pinned** IP. Re-validate on redirects.
- Enforcement is unconditional and runs for every profile (the existing SSRF guard becomes the `internal` profile plus these additions).

**Acceptance criteria:**
```
Given a task on the "internal" profile targeting a non-allowlisted host,
When delivery is attempted,
Then it is blocked before any socket connects and the task fails with a policy error.

Given a "public-crawl" task whose URL resolves to 169.254.169.254 (or any private range),
When delivery is attempted,
Then it is blocked (DNS-pinned) even if the hostname is public.
```

### 6.6 Status, DLQ & replay (R6)

- `GET /api/tasks/{id}` — status + attempt history (workspace-scoped).
- `GET /api/dlq?workspace=...` — list dead tasks.
- CLI: `tc artisan tasks:dlq:list`, `tasks:dlq:show <id>`, `tasks:dlq:replay <id|--type=...>` (re-enqueues with a fresh attempt count and a new delivery `Idempotency-Key` group).

---

## 7. Execution model & shared-hosting constraints

- **Heartbeat, not daemon.** `scheduler:execute-due` (every minute) is the sole executor. Each tick: (1) reap timed-out in-flight, (2) claim a bounded batch honoring per-type + global caps and fairness, (3) deliver with short connect/read timeouts, (4) settle or reschedule. `scheduler:retry-due` and `scheduler:maintenance` retain their v0 roles; maintenance also prunes idempotency keys and ages DLQ per retention.
- **Wall-clock budget (R5).** A tick MUST stop claiming new work at `max_execution_time - safety_margin` (configurable, e.g. margin 5s) so it is never killed mid-delivery. Unfinished due work is picked up next tick.
- **Overlap-safe.** MySQL-backed claim (atomic `UPDATE ... WHERE status='pending' ... LIMIT n` or `SELECT ... FOR UPDATE SKIP LOCKED`) guarantees no double-claim if ticks overlap.
- **Concurrency accounting.** In-flight count per task type and globally is derived from `claimed`/`delivering` rows; claiming filters by remaining capacity (`cap - inflight`). `weight` lets one heavy task consume multiple capacity units.
- **Heavy compute stays remote (N1).** Delivery targets that need >~20s return `202 Accepted` immediately, do the work asynchronously, and report completion via their own follow-up task or status callback. TaskConnect never holds a socket open for minutes.
- **cron floor caveat.** Some budget plans throttle cron below 1/min. Document the minimum supported interval and degrade gracefully (larger batches per tick) rather than assuming per-minute.

---

## 8. Security requirements (hard constraints)

- **S1. No hardcoded secrets.** Signing keys, client secrets, encryption keys — all from `.env`. Build test fails if any credential-like literal ships in `dist/`.
- **S2. Delegated identity, not shared secrets.** Callbacks authenticate with a GrandpaSSOn **client-credentials** token (scope `tasks:callback`), obtained by TaskConnect's own service client and refreshed before expiry. Static shared callback secrets are removed.
- **S3. Payload integrity + anti-replay.** Every callback is HMAC-signed over `timestamp + nonce + body`; receivers enforce freshness + nonce non-replay (S8 pairs with the notes-app receiver).
- **S4. Inbound authZ.** Submission requires a GrandpaSSOn token with scope `tasks:write` and `aud` covering the target `workspace_id`; introspected (opaque tokens) before acceptance.
- **S5. Egress is deny-by-default.** Non-`internal` profiles are opt-in per task type; DNS-pinned; private ranges hard-denied (§6.5).
- **S6. Secrets encrypted at rest.** Task payload secrets and stored tokens encrypted (extends v0 encrypted-secrets); tokens stored as hashes where feasible.
- **S7. Constant-time comparison** for HMAC and token checks.
- **S8. Rate limiting & lockout** on submission and (where applicable) callback verification (R15), DB-backed.
- **S9. Pure-PHP crypto only** (`sodium`/`openssl`); no shelling out (`exec` disabled on target hosts).
- **S10. Audit** enqueue, claim, delivery outcome, DLQ transitions, replay, and auth failures — workspace-scoped.

> **Dependency on GrandpaSSOn:** requires the `tasks:callback` scope (already in the GrandpaSSOn v1 spec) **and** a `tasks:write` scope for submitters. If `tasks:write` is not yet in GrandpaSSOn's vocabulary, raise it as a cross-repo ticket rather than inventing a local auth path (see §12/Q1).

---

## 9. Observability & failure handling

- **Every task exposes** status, attempt count, last error, timing, egress profile used, and (if applicable) pipeline + node.
- **DLQ is the single failure surface (R6):** no task disappears silently; exhaustion → DLQ with enough context to diagnose and replay.
- **Alerting (R13/P1):** DLQ arrival triggers a per-workspace webhook/email.
- **Metrics (R18/P2):** queue depth, in-flight by type, DLQ size, per-type p50/p95 latency, tick duration vs budget.

---

## 10. Cross-project interfaces

### 10.1 Notes app (task producer + callback receiver)
- Submits tasks/pipelines with `tasks:write` tokens; enforces its own workspace RBAC before submitting.
- Receives callbacks; verifies bearer + HMAC + freshness + nonce; dedupes on `Idempotency-Key`; performs the heavy work (convert/crawl-extract/embed/publish) within its own request limits, one unit per task.
- Long jobs answer `202` and report completion via a follow-up task.

### 10.2 GrandpaSSOn (identity provider)
- TaskConnect is a registered **service client** (scope `tasks:callback`); obtains/refreshes client-credentials tokens for outbound calls.
- Validates inbound submission tokens via GrandpaSSOn introspection.
- GrandpaSSOn's own maintenance (token GC, audit retention) MAY be scheduled as TaskConnect recurring tasks, closing the loop between the two services.

---

## 11. Deployment, migrations & stop line

- **Platform:** PHP 8.2+, MySQL 8.0+, Apache/LiteSpeed + `mod_rewrite`, document root `public/`, one-minute cron, deploy-by-zip. Preserve Docker dev (`tc up/test/e2e/release`) and Playwright E2E.
- **Migrations:** all new tables/columns via idempotent migrations; no destructive change to v0 tables; `workspace_id` backfilled safely for any existing rows (default/placeholder workspace strategy documented).
- **Config:** new settings in `.env.example` with defaults (batch size, wall-clock margin, per-type caps, global ceiling, dedupe/coalesce windows, retention).
- **Release:** `tc release` emits a working `dist/` zip with **no secrets** (verified by S1 test).

**P0 stop line (definition of done for this spec's first cut):**
> Workspace-scoped tasks; enqueue + delivery idempotency; task types with priority/weight/concurrency caps + global ceiling; bounded overlap-safe heartbeat; dead-letter queue with inspect + replay; egress profiles with hardened DNS-pinned SSRF; callbacks authenticated via GrandpaSSOn bearer + HMAC and inbound submission introspected; no static shared secret; no secret in the zip; `tc test` and `tc release` green. **Stop here and request review** before starting P1 (pipelines, coalescing, fairness, UI).

---

## 12. Open questions (resolve before/with implementation)

| # | Question | Default if unresolved | Owner |
|---|----------|-----------------------|-------|
| Q1 | Add `tasks:write` to GrandpaSSOn now, or gate submission by a TaskConnect-local API key until then? | Add `tasks:write` to GrandpaSSOn (cross-repo ticket); no local auth path. | Joe |
| Q2 | Does v0 already have first-class recurring + delayed schedules, or must R16 formalize them? | Assume delayed (`run_at`) needs formalizing for reminders; recurring reuses existing scheduler. | Joe |
| Q3 | Should TaskConnect ever fetch external URLs directly (`public-crawl`), or always delegate crawling to the notes app? | Default to delegating; ship `public-crawl` profile but leave it unused until a concrete need. | Joe |
| Q4 | Default per-type concurrency caps and global in-flight ceiling for a typical Hostinger shared plan? | Global 4 in-flight; `document.convert` 2, `site.crawl` 1, `kb.index` 2, `note.reminder` 4 — all env-configurable. | Joe |
| Q5 | Opaque-token introspection on every callback vs. short-lived cached validation? | Introspect inbound submissions; for outbound, TaskConnect caches its own client-credentials token until near expiry. | Joe |
| Q6 | DLQ retention and idempotency-key TTL windows? | DLQ 30 days; enqueue idempotency window 24h; delivery key lives with the task. | Joe |

---

*This document extends `docs/http-task-scheduler-spec.md` and pairs with `grandpasson-spec-v1-extension.md`. Implement P0 in order, keep v0 green, and stop at the §11 stop line for review.*
