# MVP P5 Design — Acceptance polish

## Goal

Close the highest remaining §28 / §18 UI gaps without a full wizard rewrite or broad a11y/dependency audit.

## Scope

1. **Run detail (§18.7)** — show `scheduled_for`, masked `idempotency_key`, `next_attempt_at` / attempt `next_retry_at`, and short terminal-failure copy.
2. **Dashboard discoverability (§18.3 / §28.4)** — create-task CTA; link dead/retry/failed stats to run list; add `run_state` filter on task-run index; recent runs list (small).
3. **Schedule next-3 preview** — authenticated preview endpoint reusing `ScheduleCalculator::previewNext`; show in wizard schedule step.
4. **Locale-aware `schedule_human`** — fix SPA typing/rendering for `{kind,parts}` payloads.
5. **Playwright authenticated slice + `tc e2e` wiring** — login → dashboard → tasks/wizard when `E2E_*` set; root/`tc` e2e script; document browser install. Defer full cron→receiver journey.

## Non-goals

- Full 8-step wizard rewrite
- Full §26.4 e2e (cron + receiver)
- Broad a11y / npm-composer vulnerability audit
- Visual redesign
