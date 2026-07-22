# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Cross-repo GrandpaSSOn handoff: accept `workspace/<id>` aud forms; document broker follow-up at grandpasson#55 (`docs/architecture/grandpasson-cross-repo.md`) (closes #26)
- v1 Extension P0 epic closeout: R1–R9 complete; see `docs/architecture/p0-epic-closeout.md` (closes #16)
- v1 Extension P1 epic closeout: R10–R15 complete; see `docs/architecture/p1-epic-closeout.md` (closes #27)
- v1 Extension R17: WFQ deficit claiming (`SCHEDULER_FAIRNESS_MODE`) + optional claim-time priority preemption; see `docs/architecture/fairness.md`
- v1 Extension R18: Prometheus-style `GET /api/v1/platform/metrics` (platform admin); queue depth, in-flight, DLQ, per-type latency, tick vs budget; see `docs/architecture/metrics.md`
- v1 Extension open-questions audit: Q1–Q6 defaults documented in `docs/architecture/open-questions.md` (closes #35)
- v1 Extension R16: top-level delayed `run_at` synthesizes once schedules; optional five-field `cron` schedule kind; see `docs/architecture/delayed-schedules.md`
- v1 Extension R14: operator SPA task governance columns, DLQ inspect/replay UI, pipeline instance visibility; REST `…/dlq` + `…/pipeline-instances`
- v1 Extension R15: DB-backed submission rate limits per workspace (`rate_limit_buckets`, `Retry-After` on 429); see `docs/architecture/submit-rate-limiting.md`
- v1 Extension R13: per-workspace DLQ email/webhook alerts (`notifications.*` on environments); audited `dlq.alert.*`; see `docs/architecture/dlq-alerting.md`
- v1 Extension R12: weighted round-robin claim fairness across workspaces (`SCHEDULER_FAIRNESS_WORKSPACE_WEIGHT`); see `docs/architecture/fairness.md`
- v1 Extension R11: `coalesce_key` + `SCHEDULER_COALESCE_WINDOW_SECONDS` collapses bursty workspace enqueues (incl. pipeline `publish.build`); see `docs/architecture/coalesce.md`
- v1 Extension R10: named pipeline DAGs (`on_success` / `on_failure` / `depends_on`) with `convert-index-publish` template; workspace-scoped instance API; settlement materializes successors; see `docs/architecture/pipelines.md`
- v1 Extension R8: GrandpaSSOn client-credentials + HMAC callback headers (outbound) and introspected `tasks:write` inbound with workspace `aud` check (dual-mode; flags default off); see `docs/architecture/grandpasson-auth.md`
- v1 Extension R9: `validate-release.sh` fails on `.env` / private keys / credential-like literals; documents GrandpaSSOn placeholders; see `docs/architecture/secret-hygiene.md`
- v1 Extension R7: named egress profiles (`internal` / `public-crawl` / `api`) enforced before DNS-pinned connect; see `docs/architecture/egress-profiles.md`
- v1 Extension R6: DLQ CLI `tasks:dlq:list|show|replay` over `run_state=dead`; `RETENTION_DEAD_RUNS_DAYS` (default 30); see `docs/architecture/dlq.md`
- v1 Extension R5: `scheduler:execute-due` / `retry-due` enforce wall-clock budget via chunked claim-execute (`SCHEDULER_TARGET_DURATION_SECONDS`, `SCHEDULER_BUDGET_SAFETY_MARGIN_SECONDS`, `SCHEDULER_CLAIM_CHUNK`)
- v1 Extension R4: named task types (`document.convert`, `site.crawl`, …) with priority/weight/timeout/egress defaults, per-type concurrency caps + global in-flight ceiling; claimer honors capacity by weight (`config/task_types.php`, `TASK_TYPE_*`)
- v1 Extension R3: outbound deliveries send canonical `Idempotency-Key` (stable per run) plus deprecated `X-Task-Idempotency-Key` alias; see `docs/architecture/callback-contract.md`
- v1 Extension R2: required workspace-scoped `Idempotency-Key` on task create / run-now; 200 on create replay; `IDEMPOTENCY_ENQUEUE_TTL_HOURS`
- v1 Extension R1: `workspace_id` API alias for Environment; audit logs store `environment_id` with `?workspace_id=` filter (`docs/architecture/workspace.md`)
## [1.2.0] — 2026-07-22

### Added

- Dashboard `failed_tasks` count linking to `/tasks?last_run_state=dead`
- Task list: human-readable schedule column, empty-state CTA, row Duplicate
- Endpoint profile connection Test from the list page
- Secret `usage_count` on API responses + in-use archive confirmation
- Auth 429 responses preserve `Retry-After`; SPA surfaces wait seconds when present
- Wizard query-params editor + task detail display
- Run list cancel/retry actions
- Platform health: maintenance heartbeat, stale execute/retry degrade, retention summary
- Read-only retention defaults API/UI (`GET /platform/retention`) + deployment docs
- Secret rotate confirmation; API key permission labels; 429 i18n mapping
- Bulk task pause/resume API + multi-select task list actions
- Task list `schedule_kind` filter
- User preference `failure_emails_enabled` (Settings → Notifications) honored by FailureNotifier
- Endpoint profile TLS-off warning/confirm in the SPA; audit summaries include `verify_tls`
- `scripts/validate-release.sh` run after `tc release`
- Task wizard expansion: headers editor, separate Retry/Test steps, sync profile test, review security warnings
- Task list search, status/last-run filters, and sortable columns (`q`, `definition_status`, `last_run_state`, `sort`, `order`)
- Login-page axe-core a11y smoke (Vitest) and global `:focus-visible` outline
- Dependency audit process doc + manual §26.4 acceptance checklist
- i18n’d store/API fallback error strings and main nav aria-label
- Schedule preview API (`POST …/schedules/preview`) and wizard next-3 occurrence preview
- Dashboard recent-run list, create-task CTA, and linked dead/retry/failed stats (`run_state` filter on task-run list)
- Run detail diagnostics: scheduled_for, masked idempotency key, next attempt/retry times, terminal-failure copy
- Locale-aware `schedule_human` rendering helper
- Root `npm run e2e` / `tc e2e` proxy to the frontend Playwright suite
- Operator UI for tenant outbound host allowlist (Settings) and task success status ranges (create/edit wizard)
- Playwright smoke journey: login → forgot-password; optional authenticated dashboard when `E2E_EMAIL` / `E2E_PASSWORD` are set
- Frontend helpers/tests for parsing success status range strings
- Tenant `outbound_allow_hosts` for SSRF host allowlisting (API + delivery path)
- Per-task `success_status_ranges` on retry policy
- API `Idempotency-Key` middleware for task create and run-now
- Playwright smoke wiring (`frontend/e2e`, `npm run e2e`)
- Enforce `max_retry_window_seconds` in retry decisions
- Auth endpoint rate limiting (`login`, `forgot-password`, `reset-password`)
- Task-run list pagination (`limit` / `before`) and `task_id` filter
- Feature coverage for task lifecycle and password reset
- Root `LICENSE` (MIT)

### Changed

- Application version default bumped to **1.2.0** (`APP_VERSION` / platform health)
- Stronger scheduled-occurrence uniqueness coverage for overlapping claimers

## [1.1.0] — 2026-07-21

### Added

- **P0:** Pending/manual/test run execution via MySQL claim leases + cron (`PendingRunClaimer`), failure notification email, archived-environment guards
- **P1:** Operator SPA workflow against `/api/v1` (tasks, runs, secrets, endpoint profiles, members, API keys), task/run authorization and API key scopes
- Multi-tenant HTTP task scheduler MVP on shared hosting (PHP 8.2+, MySQL 8.0+, per-minute cron)

### Fixed

- Auth holes around task operate scopes and read-only viewer restrictions
- Manual/test/retry runs no longer stuck pending without a queue worker

[Unreleased]: https://github.com/suporterfid/taskconnect/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/suporterfid/taskconnect/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/suporterfid/taskconnect/releases/tag/v1.1.0
