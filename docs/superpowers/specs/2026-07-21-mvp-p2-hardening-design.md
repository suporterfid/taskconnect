

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


## Goal

Close remaining MVP acceptance gaps from `docs/http-task-scheduler-spec.md` Â§28.2â€“Â§28.5 after P0/P1: reliability proof, API hardening, missing feature tests, and release hygiene.

## Scope (this batch)

1. **Reliability**
   - Overlapping-claimer coverage for scheduled occurrence uniqueness
   - Enforce `max_retry_window_seconds` in `RetryDecider` / `AttemptExecutor`
2. **API hardening**
   - Throttle auth endpoints (`login`, `forgot-password`, `reset-password`)
   - Cursor-style pagination for task-run listing (`limit` + `before`/`cursor`)
   - Optional `task_id` filter on run list (frontend already uses query param client-side)
3. **Tests**
   - Task CRUD/lifecycle feature tests
   - Password reset happy path
4. **Hygiene**
   - `LICENSE` (MIT, matching `composer.json`)
   - `CHANGELOG.md` for v1 MVP notes
   - Mark P0/P1 plan complete; add P2 plan doc

## Deferred (later)

- Playwright e2e (still unwired)
- Tenant outbound allowlists
- Custom per-task success status ranges
- GitHub Actions (intentionally removed)

## Approach

Keep MySQL claim leases; no brokers. Prefer small, testable increments matching existing Phase0/1/Scheduling patterns.
