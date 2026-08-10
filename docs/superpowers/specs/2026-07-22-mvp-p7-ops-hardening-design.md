

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


## Goal

Finish operator ops gaps: bulk task pause/resume, TLS-off warnings, failure-email prefs, schedule_kind filter, and release-zip validation â€” shared-hosting safe.

## Scope

1. Task list `schedule_kind` filter (API already supports it).
2. Bulk pause/resume API + multi-select UI.
3. Endpoint-profile TLS disable confirm/warning in SPA (+ audit clarity).
4. User preference to opt out of dead-run failure emails; Settings notifications section.
5. `tc` release validation script for zip contents / sha256.

## Non-goals

- Full cronâ†’receiver Playwright
- 8-step wizard Auth/Request Data rewrite
- Notification rule builder / per-tenant retention UI
