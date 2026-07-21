# AGENTS.md

Guidance for AI agents working in this repository.

## Read first

1. **Hard constraints** — `.cursor/rules/hard-constraints.mdc` (always on) and `CLAUDE.md`
2. **Product** — `docs/http-task-scheduler-spec.md` (Phase 2–4 are aspirational unless code/tests exist)
3. **Deploy / cron** — `docs/deployment/`

File-scoped Cursor rules under `.cursor/rules/` cover backend layering, scheduler/SSRF, frontend, and tests.

## Quick facts

| Item | Value |
|------|--------|
| Stack | Laravel 12 + Vue 3 SPA (`frontend/`) |
| Runtime target | Shared hosting: PHP 8.2+, MySQL 8.0+, minute cron, docroot `public/` |
| Async model | MySQL claim leases + `scheduler:*` artisan — not queue workers |
| Dev | Docker only via `scripts/tc.ps1` (Windows) or `scripts/tc.sh` (Unix) |
| Issues | Track work in GitHub `suporterfid/taskconnect` |

## Do not

- Require Redis, brokers, Horizon, Octane, or long-running workers
- Install PHP/Composer/Node on the host
- Put Eloquent models in `app/Models/` (use `app/Infrastructure/Persistence/Eloquent/`)
- Bypass outbound SSRF / DNS-pinned HTTP for user URLs
- Commit Packagist mirror configuration
