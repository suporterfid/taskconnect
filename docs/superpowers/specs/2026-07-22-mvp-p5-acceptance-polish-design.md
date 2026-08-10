

> *This project is part of the [IROH Initiative](https://github.com/suporterfid/iroh). See the main hub for manifesto, branding, and ecosystem index.*


## Goal

Close the highest remaining Â§28 / Â§18 UI gaps without a full wizard rewrite or broad a11y/dependency audit.

## Scope

1. **Run detail (Â§18.7)** â€” show `scheduled_for`, masked `idempotency_key`, `next_attempt_at` / attempt `next_retry_at`, and short terminal-failure copy.
2. **Dashboard discoverability (Â§18.3 / Â§28.4)** â€” create-task CTA; link dead/retry/failed stats to run list; add `run_state` filter on task-run index; recent runs list (small).
3. **Schedule next-3 preview** â€” authenticated preview endpoint reusing `ScheduleCalculator::previewNext`; show in wizard schedule step.
4. **Locale-aware `schedule_human`** â€” fix SPA typing/rendering for `{kind,parts}` payloads.
5. **Playwright authenticated slice + `tc e2e` wiring** â€” login â†’ dashboard â†’ tasks/wizard when `E2E_*` set; root/`tc` e2e script; document browser install. Defer full cronâ†’receiver journey.

## Non-goals

- Full 8-step wizard rewrite
- Full Â§26.4 e2e (cron + receiver)
- Broad a11y / npm-composer vulnerability audit
- Visual redesign
