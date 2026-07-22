# MVP P8 Design — Request fidelity + operator polish

## Goal

Close remaining high-value §9 / §18 / §21 / §22 / §28 gaps without browser-heavy e2e or the 8-step wizard rewrite.

## Scope

1. Wizard query-params editor + task detail display (`query` / `query_json`).
2. Run list cancel/retry row actions.
3. Platform health: maintenance heartbeat + degrade on stale execute/retry.
4. Read-only retention defaults in Settings (+ docs for `RETENTION_*`).
5. Secret rotate confirm + impact copy.
6. §28.5 polish: i18n placeholders, 429 mapping, API key permission labels.

## Non-goals

- Full cron→receiver Playwright
- Standalone Auth/Request Data wizard steps
- Notification rule builder / per-tenant retention UI
