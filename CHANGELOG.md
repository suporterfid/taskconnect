# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Feature coverage for task lifecycle (create, activate, pause/resume, run-now, duplicate, archive) and cross-tenant isolation
- Password reset happy-path feature test
- Root `LICENSE` (MIT) and this changelog

### Changed

- P2 hardening in progress: retry window enforcement, overlapping claim coverage, auth throttling, task-run pagination/`task_id` filter (see `docs/superpowers/plans/2026-07-21-mvp-p2-hardening.md`)

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
