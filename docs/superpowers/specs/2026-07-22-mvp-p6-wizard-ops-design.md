# MVP P6 Design — Wizard ops + task list + quality gate

## Goal

Close remaining §28.1 operator workflow gaps (test before activate), §18.6 task-list discoverability, and §28.5 quality-gate evidence — without full 8-step rewrite or browser-heavy e2e.

## Scope

1. Wizard: separate Retry step; headers editor; Test step (profile sync test or draft-task test); review security/retry summary; auth shown via selected profile summary.
2. Task list: API `q` / `definition_status` / `last_run_state` / `sort` + SPA controls.
3. A11y: `:focus-visible` ring, i18n main nav label, Vitest+axe smoke on login (and wizard if cheap).
4. Dependency audit doc from `composer audit` / `npm audit` with mitigations.
5. Replace hard-coded English API/store fallbacks; short manual §26.4 acceptance checklist.

## Non-goals

- Full 8-step Auth/Request Data rewrite
- Bulk pause/resume
- Full cron→receiver Playwright in CI/slim node image
