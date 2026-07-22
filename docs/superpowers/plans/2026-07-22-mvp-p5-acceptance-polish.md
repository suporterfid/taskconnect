# MVP P5 Acceptance Polish — Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Close §28 UI/operability gaps: run diagnostics, dashboard discoverability, schedule preview, schedule_human rendering, thin authenticated Playwright.

**Architecture:** Thin API additions (preview + run_state filter + dashboard recent runs) on existing Domain/Application services; SPA pages consume them. No queue/broker changes.

**Tech Stack:** Laravel 12, Vue 3 + TypeScript, Playwright, Docker via `tc`.

## Global Constraints

- Shared hosting: PHP 8.2+, MySQL 8.0+, minute cron; no required Redis/workers
- Docker-only for PHP/Node/Composer/npm
- Eloquent models stay under `app/Infrastructure/Persistence/Eloquent/`
- Track work in GitHub issues when API allows

---

### Task 1: Task-run `run_state` filter + dashboard recent runs

- [ ] Extend `TaskRunController@index` to accept `run_state`
- [ ] Extend `DashboardController` to return `recent_run_items` (id, task name/id, state, finished_at)
- [ ] Feature tests

### Task 2: Schedule preview API

- [ ] `POST .../schedules/preview` (or tasks preview) returning next 3 UTC occurrences from schedule payload
- [ ] Reuse `ScheduleCalculator::previewNext`
- [ ] Feature test

### Task 3: Run detail UI polish

- [ ] Show scheduled_for, masked idempotency key, next attempt/retry, terminal failure copy
- [ ] i18n en/pt-BR

### Task 4: Dashboard UI polish

- [ ] Create-task CTA; link stats to `/runs?run_state=…`; show recent runs list
- [ ] i18n

### Task 5: Wizard schedule preview + schedule_human helper

- [ ] `scheduleHuman.ts` + vitest
- [ ] Wizard calls preview on schedule step; display next 3
- [ ] Task detail/list use helper for `schedule_human`

### Task 6: Playwright + tc e2e

- [ ] Expand smoke with authenticated tasks/wizard path
- [ ] Wire root/`tc e2e` to frontend script
- [ ] Document playwright install in CLAUDE/deployment note
- [ ] CHANGELOG + PR
