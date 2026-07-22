# MVP P7 Design — Ops hardening

## Goal

Finish operator ops gaps: bulk task pause/resume, TLS-off warnings, failure-email prefs, schedule_kind filter, and release-zip validation — shared-hosting safe.

## Scope

1. Task list `schedule_kind` filter (API already supports it).
2. Bulk pause/resume API + multi-select UI.
3. Endpoint-profile TLS disable confirm/warning in SPA (+ audit clarity).
4. User preference to opt out of dead-run failure emails; Settings notifications section.
5. `tc` release validation script for zip contents / sha256.

## Non-goals

- Full cron→receiver Playwright
- 8-step wizard Auth/Request Data rewrite
- Notification rule builder / per-tenant retention UI
