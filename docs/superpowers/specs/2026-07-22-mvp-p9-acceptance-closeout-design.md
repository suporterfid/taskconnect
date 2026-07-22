# MVP P9 Design — Acceptance closeout

## Goal

Close remaining list-discoverability gaps for §28 and cut a clean v1.2 quality gate.

## Scope

1. Dashboard `failed_tasks` count + link to filtered task list.
2. Task list: `schedule_human` column, empty-state CTA, row Duplicate.
3. Endpoint profile Test from list.
4. Secret `usage_count` + confirm copy when in use.
5. v1.2 bump (CHANGELOG, APP_VERSION, README/docs), 429 Retry-After surfacing, dependency-audit refresh attempt.

## Non-goals

- Full cron→receiver Playwright
- 8-step wizard Auth/Request Data rewrite
- Notification rule builder / per-tenant retention UI
- Environment restore
