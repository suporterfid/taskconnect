# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

- Stronger scheduled-occurrence uniqueness coverage for overlapping claimers

## [1.1.0] — 2026-07-21

### Added

- **P0:** Pending/manual/test run execution via MySQL claim leases + cron (`PendingRunClaimer`), failure notification email, archived-environment guards
- **P1:** Operator SPA workflow against `/api/v1` (tasks, runs, secrets, endpoint profiles, members, API keys), task/run authorization and API key scopes
- Multi-tenant HTTP task scheduler MVP on shared hosting (PHP 8.2+, MySQL 8.0+, per-minute cron)

### Fixed

- Auth holes around task operate scopes and read-only viewer restrictions
- Manual/test/retry runs no longer stuck pending without a queue worker

[Unreleased]: https://github.com/suporterfid/taskconnect/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/suporterfid/taskconnect/releases/tag/v1.1.0
